<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReEnrollLearnerRequest;
use App\Http\Requests\StoreExcuseSlipRequest;
use App\Http\Requests\StoreLearnerRequest;
use App\Http\Requests\UpdateLearnerRequest;
use App\Http\Requests\WithdrawLearnerRequest;
use App\Models\Learner;
use App\Models\LearnerSchoolYear;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Teacher;
use App\Support\ClassSession;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LearnerController extends Controller
{
    // Optional ?section=<id>: teacher()->learners() already restricts to
    // sections this teacher owns, so an ID for another teacher's section
    // just yields an empty list here, never someone else's roster.
    // Only learners still enrolled this year count; ?withWithdrawn=1 (the class list) adds the withdrawn ones.
    public function index(Request $request, #[CurrentUser] Teacher $teacher): JsonResponse
    {
        $filters = $request->validate(['section' => ['nullable', 'integer'], 'ready' => ['nullable', 'boolean'], 'withWithdrawn' => ['nullable', 'boolean']]);
        $sectionId = $filters['section'] ?? null;
        $statuses = $request->boolean('withWithdrawn') ? ['active', 'withdrawn'] : ['active'];

        $learners = $teacher->learners()
            ->whereHas('enrollments', fn ($e) => $e->whereIn('status', $statuses)
                ->when($sectionId, fn ($e, $id) => $e->where('section_id', $id))
                ->whereHas('schoolYear', fn ($y) => $y->where('status', 'active')))
            ->when($request->boolean('ready'), fn ($q) => $this->readyForGrade1($q))
            ->with('latestEnrollment.section')
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        return response()->json($learners->map(fn (Learner $learner) => $learner->toListArray()));
    }

    // The rule itself lives in LearnerSchoolYear::whereReadyForGrade1; this list reads the active year.
    private function readyForGrade1($query)
    {
        return $query->whereHas('enrollments', fn ($e) => LearnerSchoolYear::whereReadyForGrade1($e)
            ->whereHas('schoolYear', fn ($y) => $y->where('status', 'active')));
    }

    // Scoped to the teacher's own learners, so another teacher's learner reads as not found.
    public function show(#[CurrentUser] Teacher $teacher, int $learner): JsonResponse
    {
        return response()->json($this->findOwned($teacher, $learner)->toProfileArray());
    }

    public function update(UpdateLearnerRequest $request, #[CurrentUser] Teacher $teacher, int $learner): JsonResponse
    {
        $learner = $this->findOwned($teacher, $learner);
        $data = $request->validated();

        DB::transaction(function () use ($request, $data, $learner) {
            // The name is fixed once the learner is added, so only the LRN changes here.
            $learner->update(['lrn' => $data['lrn'] ?? null]);

            $learner->address()->updateOrCreate([], [
                'house_street' => $data['houseStreet'],
                'barangay' => $data['barangay'],
                'city_municipality' => $data['cityMunicipality'],
                'province_id' => $data['provinceId'],
                'zip_code' => $data['zipCode'],
            ]);

            // Guardian N of the form is the Nth guardian by id; one beyond the existing ones is added.
            $guardians = $learner->guardians()->orderBy('id')->get();
            for ($i = 0; $i < (int) $data['guardianCount']; $i++) {
                $relationship = $data["relationshipToLearner_$i"];
                $guardian = [
                    'guardian_name' => trim($data["guardianName_$i"]),
                    'contact_number' => $data["contactNumber_$i"],
                    'relationship_to_learner' => $relationship === 'OTHER' ? trim($data["relationshipOther_$i"]) : $relationship,
                    'guardian_email' => $data["guardianEmail_$i"] ?? null,
                    'occupation' => isset($data["occupation_$i"]) ? trim($data["occupation_$i"]) : null,
                ];
                isset($guardians[$i]) ? $guardians[$i]->update($guardian) : $learner->guardians()->create($guardian);
            }

            if ($request->hasFile('photo')) {
                $old = $learner->avatar_url;
                $learner->update(['avatar_url' => $this->storePhoto($request->file('photo'))]);
                if ($old) {
                    Storage::disk('public')->delete($old);
                }
            }
        });

        return response()->json(['learnerId' => $learner->id]);
    }

    // An excuse slip turns one of the learner's absences into an excused day, with the reason the guardian gave.
    public function excuseSlip(StoreExcuseSlipRequest $request, #[CurrentUser] Teacher $teacher, int $learner): JsonResponse
    {
        $enrollment = $this->findOwned($teacher, $learner)->latestEnrollment;
        $data = $request->validated();
        if ($enrollment?->status !== 'active') {
            throw ValidationException::withMessages(['date' => "This learner is withdrawn, so their attendance can't be changed."]);
        }

        $absence = DB::table('attendance')->where(['enrollment_id' => $enrollment?->id, 'attendance_date' => $data['date'], 'status' => 'absent']);
        if (! $absence->exists()) {
            throw ValidationException::withMessages(['date' => 'Pick a day this learner was absent.']);
        }

        DB::transaction(function () use ($absence, $enrollment, $data) {
            // Re-checked under the enrollment lock, so a withdrawal that just committed refuses the slip.
            if (! ClassSession::lockActive([$enrollment->id])) {
                throw ValidationException::withMessages(['date' => "This learner is withdrawn, so their attendance can't be changed."]);
            }
            $now = now();
            $absence->update(['status' => 'excused', 'excuse_reason' => $data['reason'], 'updated_at' => $now]);
            // That day's class roster follows the daily record.
            DB::table('classroom_session_learners')->where('enrollment_id', $enrollment->id)
                ->whereIn('classroom_session_id', DB::table('classroom_sessions')->where('session_date', $data['date'])->select('id'))
                ->update(['attendance_status' => 'excused', 'updated_at' => $now]);
        });

        return response()->json(['success' => true]);
    }

    // Withdraws the learner from this school year: their active-year enrollment becomes withdrawn on the given day,
    // with the optional reason. Nothing is deleted. The profile, every record of the enrollment and every other
    // enrollment stay as they are, and a later school year can re-enroll them.
    public function withdraw(WithdrawLearnerRequest $request, #[CurrentUser] Teacher $teacher, int $learner): JsonResponse
    {
        $learner = $this->findOwned($teacher, $learner);
        $data = $request->validated();
        $enrollment = $learner->enrollments()
            ->whereHas('schoolYear', fn ($y) => $y->where('status', 'active'))
            ->whereHas('section', fn ($s) => $s->where('teacher_id', $teacher->id))
            ->with('schoolYear')
            ->first();
        if ($enrollment?->status !== 'active') {
            throw ValidationException::withMessages(['learner' => 'Only a learner currently enrolled this school year can be withdrawn.']);
        }

        $date = $data['withdrawnOn'];
        $year = $enrollment->schoolYear;
        $error = match (true) {
            $date > ClassSession::today() => "The withdrawal date can't be in the future.",
            $date < $enrollment->enrolled_on->toDateString() => "The withdrawal date can't be before the learner was enrolled ({$enrollment->enrolled_on->format('M j, Y')}).",
            $year->start_date && $date < $year->start_date->toDateString() => "The withdrawal date can't be before SY {$year->name} starts ({$year->start_date->format('M j, Y')}).",
            $year->end_date && $date > $year->end_date->toDateString() => "The withdrawal date can't be after SY {$year->name} ends ({$year->end_date->format('M j, Y')}).",
            default => null,
        };
        if ($error) {
            throw ValidationException::withMessages(['withdrawnOn' => $error]);
        }

        DB::transaction(function () use ($teacher, $enrollment, $date, $data) {
            // FOR UPDATE on the section conflicts with the FOR SHARE that ClassroomController::start takes first, so a
            // class start and a withdrawal in one section run one after the other: a start that went first has its class
            // open (refused below), and a start that waits reads the learner as withdrawn and leaves them off the roster.
            // The teacher filter is re-checked after the wait, so a handover that just committed wins.
            if (! Section::whereKey($enrollment->section_id)->where('teacher_id', $teacher->id)->lockForUpdate()->first(['id'])) {
                throw ValidationException::withMessages(['learner' => 'This section is no longer yours.']);
            }
            // The same lock attendance, ratings and learner joins take, then the status as it is now.
            if (! ClassSession::lockActive([$enrollment->id])) {
                throw ValidationException::withMessages(['learner' => 'Only a learner currently enrolled this school year can be withdrawn.']);
            }
            $classOpen = DB::table('classroom_sessions')
                ->join('section_modules', 'section_modules.id', '=', 'classroom_sessions.section_module_id')
                ->where('section_modules.section_id', $enrollment->section_id)
                ->whereIn('classroom_sessions.status', ['scheduled', 'ongoing'])
                ->exists();
            if ($classOpen) {
                throw ValidationException::withMessages(['learner' => 'A class is running in this section. End it first, then withdraw the learner.']);
            }
            // Under the enrollment lock, so no attendance mark or rating can land after this read.
            $latest = $this->latestRecordDate($enrollment->id);
            if ($latest && $date < $latest) {
                throw ValidationException::withMessages(['withdrawnOn' => 'This learner has records up to '.Carbon::parse($latest)->format('M j, Y').'. Pick that day or later.']);
            }

            $enrollment->update([
                'status' => 'withdrawn',
                'withdrawn_on' => $date,
                'withdrawal_reason' => filled($data['reason'] ?? null) ? trim($data['reason']) : null,
            ]);
        });

        return response()->json(['learnerId' => $learner->id, 'enrollmentId' => $enrollment->id]);
    }

    // The latest Manila calendar day with any record of the enrollment, or null. Date columns count as they are;
    // timestamps (all timestamptz) become the Manila day they fall on. Progress reports are left out: they describe a
    // period, and an existing draft may still be finished after the withdrawal.
    private function latestRecordDate(int $enrollmentId): ?string
    {
        $sql = <<<'SQL'
            SELECT max(d) AS latest FROM (
                SELECT attendance_date AS d FROM attendance WHERE enrollment_id = ?
                UNION ALL
                SELECT s.session_date FROM classroom_session_learners r
                JOIN classroom_sessions s ON s.id = r.classroom_session_id WHERE r.enrollment_id = ?
                UNION ALL
                SELECT (assessed_at AT TIME ZONE 'Asia/Manila')::date FROM learner_developmental_checklists WHERE enrollment_id = ?
                UNION ALL
                SELECT (assessed_at AT TIME ZONE 'Asia/Manila')::date FROM learner_developmental_assessments WHERE enrollment_id = ?
                UNION ALL
                SELECT (greatest(assigned_at, completed_at) AT TIME ZONE 'Asia/Manila')::date FROM learner_activity_assignments WHERE enrollment_id = ?
                UNION ALL
                SELECT (greatest(t.started_at, t.demo_watched_at, t.submitted_at, t.evaluated_at) AT TIME ZONE 'Asia/Manila')::date
                FROM learner_activity_attempts t
                JOIN learner_activity_assignments a ON a.id = t.assignment_id WHERE a.enrollment_id = ?
            ) records
            SQL;

        return DB::selectOne($sql, array_fill(0, 6, $enrollmentId))->latest;
    }

    public function store(StoreLearnerRequest $request, #[CurrentUser] Teacher $teacher): JsonResponse
    {
        $year = SchoolYear::active();
        $data = $request->validated();

        $learner = DB::transaction(function () use ($request, $data, $teacher, $year) {
            $learner = Learner::create([
                'lrn' => $data['lrn'] ?? null,
                'first_name' => trim($data['firstName']),
                'middle_name' => isset($data['middleName']) ? trim($data['middleName']) : null,
                'last_name' => trim($data['lastName']),
                'extension_name' => $data['extensionName'] ?? null,
                'birth_date' => $data['birthdate'],
                'gender' => $data['gender'],
            ]);

            $learner->address()->create([
                'house_street' => trim($data['houseStreet']),
                'barangay' => trim($data['barangay']),
                'city_municipality' => trim($data['cityMunicipality']),
                'province_id' => $data['provinceId'],
                'zip_code' => $data['zipCode'],
            ]);

            for ($i = 0; $i < (int) $data['guardianCount']; $i++) {
                $relationship = $data["relationshipToLearner_$i"];
                $learner->guardians()->create([
                    'guardian_name' => trim($data["guardianName_$i"]),
                    'contact_number' => $data["contactNumber_$i"],
                    // OTHER stores the typed relationship instead of the token.
                    'relationship_to_learner' => $relationship === 'OTHER' ? trim($data["relationshipOther_$i"]) : $relationship,
                    'guardian_email' => $data["guardianEmail_$i"] ?? null,
                    'occupation' => isset($data["occupation_$i"]) ? trim($data["occupation_$i"]) : null,
                ]);
            }

            // Enrolling into the teacher's section is what makes the learner theirs.
            $learner->enrollments()->create([
                'school_year_id' => $year->id,
                'section_id' => $this->enrollableSectionId($teacher, $year, $data['gradeLevel'], $data['section']),
                'enrolled_on' => Carbon::now('Asia/Manila')->toDateString(),
                'status' => 'active',
            ]);

            if ($request->hasFile('photo')) {
                $learner->update(['avatar_url' => $this->storePhoto($request->file('photo'))]);
            }

            return $learner;
        });

        return response()->json(['learnerId' => $learner->id], 201);
    }

    // Search for re-enrollment: learners this teacher taught in an earlier year who are not yet in the active one,
    // by name or LRN. Each row says why it can't be re-enrolled, if it can't, so the page can show it disabled.
    public function reEnrollable(Request $request, #[CurrentUser] Teacher $teacher): JsonResponse
    {
        $q = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q'];
        $year = SchoolYear::active();
        $like = '%'.addcslashes($q, '%_\\').'%';

        $learners = $this->taughtLearners($teacher)
            ->when($year, fn ($l) => $l->whereDoesntHave('enrollments', fn ($e) => $e->where('school_year_id', $year->id)))
            ->where(fn ($l) => $l->where('lrn', 'like', addcslashes($q, '%_\\').'%')
                ->orWhereRaw("concat_ws(' ', first_name, middle_name, last_name) ILIKE ?", [$like])
                ->orWhereRaw("concat_ws(' ', first_name, last_name) ILIKE ?", [$like]))
            ->with(['latestEnrollment.section', 'latestEnrollment.schoolYear'])
            ->orderBy('last_name')->orderBy('first_name')
            ->limit(20)
            ->get();

        return response()->json($learners->map(fn (Learner $learner) => $learner->toListArray() + [
            // gradeLevel and section above come from this same latest enrollment.
            'lastSchoolYear' => $learner->latestEnrollment?->schoolYear?->name,
            'lastStatus' => $learner->latestEnrollment?->status,
            'blockedReason' => $year ? $this->reEnrollBlock($learner, $year) : 'There is no active school year.',
        ]));
    }

    // Enrolls a learner the teacher taught before into the active school year: one new enrollment on the same
    // profile. Earlier enrollments and every record tied to them (attendance, checklist, activities, reports) are
    // left as they are, and the new enrollment starts with none.
    public function reEnroll(ReEnrollLearnerRequest $request, #[CurrentUser] Teacher $teacher, int $learner): JsonResponse
    {
        $learner = $this->taughtLearners($teacher)->findOrFail($learner);
        // The request already required the active year by name, so there is one.
        $year = SchoolYear::active();
        $data = $request->validated();

        try {
            $enrollment = DB::transaction(function () use ($learner, $teacher, $year, $data) {
                // Two re-enrollments of one learner at once run one after the other, so the second sees the first's row.
                Learner::whereKey($learner->id)->lockForUpdate()->first();
                if ($reason = $this->reEnrollBlock($learner, $year)) {
                    throw ValidationException::withMessages(['learner' => $reason]);
                }

                return $learner->enrollments()->create([
                    'school_year_id' => $year->id,
                    'section_id' => $this->enrollableSectionId($teacher, $year, $data['gradeLevel'], $data['section']),
                    'enrolled_on' => Carbon::now('Asia/Manila')->toDateString(),
                    'status' => 'active',
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // The unique (learner, school year) key backs the lock up.
            throw ValidationException::withMessages(['learner' => "Already enrolled in SY {$year->name}."]);
        }

        return response()->json(['learnerId' => $learner->id, 'enrollmentId' => $enrollment->id], 201);
    }

    // Why the learner can't be enrolled into $year, or null if they can. Grade level rules are not bypassed:
    // a learner who met Ready for Grade 1 moves on (a withdrawn or transferred year never counts as met),
    // and the Add Learner age window applies. A completed status alone decides nothing.
    private function reEnrollBlock(Learner $learner, SchoolYear $year): ?string
    {
        $enrollments = $learner->enrollments()->with('schoolYear')->get();
        if ($enrollments->contains('school_year_id', $year->id)) {
            return "Already enrolled in SY {$year->name}.";
        }
        $later = $enrollments->first(fn (LearnerSchoolYear $e) => $e->schoolYear->start_date && $year->start_date && $e->schoolYear->start_date->gte($year->start_date));
        if ($later) {
            return "Already enrolled in SY {$later->schoolYear->name}, which does not come before SY {$year->name}.";
        }
        $ready = LearnerSchoolYear::whereReadyForGrade1($learner->enrollments()->getQuery())->with('schoolYear')->first();
        if ($ready) {
            return "Met Ready for Grade 1 in SY {$ready->schoolYear->name}, so they move on to Grade 1 instead of repeating Kindergarten.";
        }
        [$earliest, $latest] = StoreLearnerRequest::birthdateBounds();
        $birthdate = $learner->birth_date->toDateString();
        if ($birthdate <= $earliest || $birthdate > $latest) {
            return StoreLearnerRequest::AGE_MESSAGE;
        }

        return null;
    }

    // The teacher's section in $year with that grade and name, refused once every section of the grade is full.
    // FOR SHARE waits out a rename in progress (SectionController::update) and then sees its new name, so a stale
    // name finds no section.
    private function enrollableSectionId(Teacher $teacher, SchoolYear $year, string $gradeLevel, string $name): int
    {
        $sectionId = $teacher->sections()
            ->where('school_year_id', $year->id)
            ->where('grade_level', $gradeLevel)
            ->where('name', $name)
            ->sharedLock()
            ->value('id');
        if (! $sectionId) {
            throw ValidationException::withMessages(['section' => 'This section was just renamed. Reopen the form and pick it again.']);
        }
        // The teacher may still pick a full section, but not once every section is full.
        // ponytail: soft cap, two enrolls at once can both see room; lockForUpdate the year's sections if a hard cap is needed.
        $siblingIds = $teacher->sections()->where('school_year_id', $year->id)->where('grade_level', $gradeLevel)->pluck('id')->all();
        $counts = Section::learnerCounts($siblingIds);
        if (collect($siblingIds)->every(fn ($id) => ($counts[$id] ?? 0) >= Section::CAPACITY)) {
            throw ValidationException::withMessages(['section' => 'All sections are full ('.Section::CAPACITY.' learners each).']);
        }

        return $sectionId;
    }

    // Learners with an enrollment, in any year, in a section this teacher holds (a handover passes past sections on too).
    private function taughtLearners(Teacher $teacher): Builder
    {
        return Learner::whereHas('enrollments.section', fn (Builder $s) => $s->where('teacher_id', $teacher->id));
    }

    private function findOwned(Teacher $teacher, int $learnerId): Learner
    {
        return $teacher->learners()
            ->with(['address.province', 'guardians', 'latestEnrollment.section', 'latestEnrollment.schoolYear'])
            ->findOrFail($learnerId);
    }

    // Re-encodes uploads as JPEGs of at most 512px so pages never pull multi-megabyte originals.
    private function storePhoto(UploadedFile $file): string
    {
        $image = imagecreatefromstring($file->get());

        // Phone cameras record rotation in EXIF, which GD ignores.
        $exif = $file->getMimeType() === 'image/jpeg' && function_exists('exif_read_data') ? @exif_read_data($file->getRealPath()) : false;
        $image = match ($exif['Orientation'] ?? 1) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        $scale = min(1, 512 / max(imagesx($image), imagesy($image)));
        $width = max(1, (int) round(imagesx($image) * $scale));
        $height = max(1, (int) round(imagesy($image) * $scale));

        // White canvas, so transparent PNG/WEBP areas do not turn black in the JPEG.
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        ob_start();
        imagejpeg($canvas, null, 82);
        $path = 'learners/'.Str::random(40).'.jpg';
        Storage::disk('public')->put($path, ob_get_clean());

        return $path;
    }
}

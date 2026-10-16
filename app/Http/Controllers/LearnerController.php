<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExcuseSlipRequest;
use App\Http\Requests\StoreLearnerRequest;
use App\Http\Requests\UpdateLearnerRequest;
use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Teacher;
use Illuminate\Container\Attributes\CurrentUser;
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
    public function index(Request $request, #[CurrentUser] Teacher $teacher): JsonResponse
    {
        $filters = $request->validate(['section' => ['nullable', 'integer'], 'ready' => ['nullable', 'boolean']]);
        $sectionId = $filters['section'] ?? null;

        $learners = $teacher->learners()
            ->when($sectionId, fn ($q, $id) => $q->whereHas(
                'enrollments',
                fn ($e) => $e->where('section_id', $id)
                    ->whereHas('schoolYear', fn ($y) => $y->where('status', 'active')),
            ))
            ->when($request->boolean('ready'), fn ($q) => $this->readyForGrade1($q))
            ->with('latestEnrollment.section')
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        return response()->json($learners->map(fn (Learner $learner) => $learner->toListArray()));
    }

    // Ready for Grade 1: every active checklist item from Q1 to Q4 is rated achieved or developing this school year.
    private function readyForGrade1($query)
    {
        $items = DB::table('developmental_checklist_items')->where(['grade_level' => CurriculumController::GRADE_LEVEL, 'is_active' => true]);
        $total = (clone $items)->count();
        // Until the checklist covers all four quarters nobody can have finished the year, so the list stays empty.
        if ((clone $items)->distinct()->count('quarter_number') < 4) {
            return $query->whereRaw('false');
        }

        return $query->whereHas('enrollments', fn ($e) => $e
            ->whereIn('status', ['active', 'completed'])
            ->whereHas('schoolYear', fn ($y) => $y->where('status', 'active'))
            ->whereRaw("(
                SELECT count(*) FROM learner_developmental_checklists c
                JOIN developmental_checklist_items i ON i.id = c.checklist_item_id
                JOIN quarters q ON q.id = c.quarter_id
                WHERE c.enrollment_id = learner_school_years.id
                  AND q.school_year_id = learner_school_years.school_year_id
                  AND q.quarter_number = i.quarter_number
                  AND i.grade_level = ? AND i.is_active
                  AND c.rating IN ('achieved', 'developing')
            ) = ?", [CurriculumController::GRADE_LEVEL, $total]));
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

        $absence = DB::table('attendance')->where(['enrollment_id' => $enrollment?->id, 'attendance_date' => $data['date'], 'status' => 'absent']);
        if (! $absence->exists()) {
            throw ValidationException::withMessages(['date' => 'Pick a day this learner was absent.']);
        }

        DB::transaction(function () use ($absence, $enrollment, $data) {
            $now = now();
            $absence->update(['status' => 'excused', 'excuse_reason' => $data['reason'], 'updated_at' => $now]);
            // That day's class roster follows the daily record.
            DB::table('classroom_session_learners')->where('enrollment_id', $enrollment->id)
                ->whereIn('classroom_session_id', DB::table('classroom_sessions')->where('session_date', $data['date'])->select('id'))
                ->update(['attendance_status' => 'excused', 'updated_at' => $now]);
        });

        return response()->json(['success' => true]);
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
                'section_id' => $teacher->sections()
                    ->where('school_year_id', $year->id)
                    ->where('grade_level', $data['gradeLevel'])
                    ->where('name', $data['section'])
                    ->value('id'),
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

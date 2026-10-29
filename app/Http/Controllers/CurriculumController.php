<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveChecklistItemRequest;
use App\Http\Requests\SaveChecklistRatingRequest;
use App\Http\Requests\SavePracticalActivityRequest;
use App\Http\Requests\StoreLessonRequest;
use App\Http\Requests\StoreLessonVideoRequest;
use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateLessonRequest;
use App\Models\Quarter;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Support\ClassSession;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CurriculumController extends Controller
{
    // The only grade level the app supports.
    public const GRADE_LEVEL = 'Kindergarten';

    public const MODULE_LOCKED = 'Modules cannot be added after the school year has started. New modules can be added once the next school year is activated, before its first quarter begins.';

    public const DAY_NAMES = [1 => 'MONDAY', 2 => 'TUESDAY', 3 => 'WEDNESDAY', 4 => 'THURSDAY', 5 => 'FRIDAY', 6 => 'SATURDAY', 7 => 'SUNDAY'];

    // Codes are derived from names ("Science Explorers" -> "science_explorers"), never typed by the teacher.
    public static function moduleCode(string $name): string
    {
        return rtrim(Str::substr(Str::slug($name, '_'), 0, 30), '_');
    }

    public static function domainCode(string $name): string
    {
        return rtrim(Str::substr(Str::slug($name, '_'), 0, 50), '_');
    }

    // Everything the checklist page needs for one of the teacher's sections: learners, items per domain, and ratings.
    public function sectionChecklist(#[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);

        $learners = DB::table('learner_school_years')
            ->join('learners', 'learners.id', '=', 'learner_school_years.learner_id')
            ->where('learner_school_years.section_id', $section->id)
            ->where('learner_school_years.status', 'active')
            ->orderBy('learners.last_name')->orderBy('learners.first_name')
            ->get(['learner_school_years.id as enrollment_id', 'learners.id as learner_id', 'learners.first_name', 'learners.middle_name', 'learners.last_name']);

        $items = DB::table('developmental_checklist_items')
            ->leftJoin('activities', 'activities.id', '=', 'developmental_checklist_items.activity_id')
            ->where(['developmental_checklist_items.grade_level' => $section->grade_level, 'developmental_checklist_items.is_active' => true])
            ->orderBy('developmental_checklist_items.quarter_number')->orderBy('developmental_checklist_items.display_order')
            ->get(['developmental_checklist_items.id', 'developmental_checklist_items.domain_id', 'developmental_checklist_items.item', 'developmental_checklist_items.quarter_number', 'developmental_checklist_items.activity_id', 'activities.name as activity_name'])
            ->groupBy('domain_id');

        // The games an added item can be linked to, per domain.
        $activities = DB::table('activities')
            ->join('lessons', 'lessons.id', '=', 'activities.lesson_id')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->where('activities.is_active', true)
            ->orderBy('activities.name')
            ->get(['activities.id', 'activities.name', 'lessons.quarter_number', 'module_domains.domain_id'])
            ->groupBy('domain_id');

        $ratings = DB::table('learner_developmental_checklists')
            ->join('quarters', 'quarters.id', '=', 'learner_developmental_checklists.quarter_id')
            ->whereIn('learner_developmental_checklists.enrollment_id', $learners->pluck('enrollment_id'))
            ->get(['learner_developmental_checklists.enrollment_id', 'learner_developmental_checklists.checklist_item_id', 'quarters.quarter_number', 'learner_developmental_checklists.rating', 'learner_developmental_checklists.remarks']);

        $recordedItems = array_flip($this->checklistItemsWithRecords($items->collapse()->pluck('id')->all()));
        $recordedActivities = array_flip($this->activitiesWithRecords($activities->collapse()->pluck('id')->all()));

        // Each linked game's latest result, so the teacher rates the skill from what the learner actually did.
        $results = ClassroomRecordController::latestResults($learners->pluck('enrollment_id'), $items->collapse()->pluck('activity_id')->filter()->unique()->values());

        return response()->json([
            'quarters' => array_map(fn (int $n) => ['quarter' => $n, 'quarterName' => $this->quarterName($n)], [1, 2, 3, 4]),
            'domains' => DB::table('developmental_domains')->where('is_active', true)->orderBy('display_order')->get(['id', 'name'])
                ->map(fn ($d) => [
                    'domainId' => $d->id,
                    'domainName' => $d->name,
                    'items' => ($items[$d->id] ?? collect())->map(fn ($i) => ['itemId' => $i->id, 'item' => $i->item, 'quarter' => $i->quarter_number, 'activityId' => $i->activity_id, 'activityName' => $i->activity_name, 'hasRecords' => isset($recordedItems[$i->id])])->values(),
                    'activities' => ($activities[$d->id] ?? collect())->map(fn ($a) => ['activityId' => $a->id, 'name' => $a->name, 'quarter' => $a->quarter_number, 'hasRecords' => isset($recordedActivities[$a->id])])->values(),
                ]),
            'learners' => $learners->map(fn ($l) => [
                'enrollmentId' => $l->enrollment_id,
                'learnerId' => $l->learner_id,
                'firstName' => $l->first_name,
                'middleName' => $l->middle_name,
                'lastName' => $l->last_name,
            ]),
            'ratings' => $ratings->map(fn ($r) => [
                'enrollmentId' => $r->enrollment_id,
                'itemId' => $r->checklist_item_id,
                'quarter' => (int) $r->quarter_number,
                'rating' => $r->rating,
                'remarks' => $r->remarks,
            ]),
            'results' => $results->map(fn ($r) => ['enrollmentId' => $r->enrollment_id, 'activityId' => $r->activity_id] + ClassroomRecordController::cell($r)),
        ]);
    }

    // Adds a skill to the end of its domain's checklist for one quarter, optionally linked to the game that practises it.
    public function storeChecklistItem(SaveChecklistItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $where = ['domain_id' => $data['domainId'], 'grade_level' => self::GRADE_LEVEL, 'quarter_number' => $data['quarter']];
        $id = DB::transaction(function () use ($where, $data) {
            Quarter::lockState();
            $this->ensureLinkable($data['activityId'] ?? null);

            return DB::table('developmental_checklist_items')->insertGetId($where + [
                'item' => trim($data['item']),
                'activity_id' => $data['activityId'] ?? null,
                'display_order' => (DB::table('developmental_checklist_items')->where($where)->max('display_order') ?? 0) + 1,
            ]);
        });

        return response()->json($this->checklistItem($id), 201);
    }

    // Renames an item or relinks its game; its domain and quarter stay, so its ratings stay with it.
    public function updateChecklistItem(SaveChecklistItemRequest $request, int $item): JsonResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($item, $data) {
            Quarter::lockState();
            // Rated, or linked to a game learners already played: its wording and link now explain past results.
            $this->ensureNoRecords(in_array($item, $this->checklistItemsWithRecords([$item]), true));
            $this->ensureLinkable($data['activityId'] ?? null);
            DB::table('developmental_checklist_items')->where('id', $item)->update([
                'item' => trim($data['item']),
                'activity_id' => $data['activityId'] ?? null,
                'updated_at' => now(),
            ]);
        });

        return response()->json($this->checklistItem($item));
    }

    private function checklistItem(int $id): array
    {
        $i = DB::table('developmental_checklist_items')
            ->leftJoin('activities', 'activities.id', '=', 'developmental_checklist_items.activity_id')
            ->where('developmental_checklist_items.id', $id)
            ->first(['developmental_checklist_items.id', 'developmental_checklist_items.item', 'developmental_checklist_items.quarter_number', 'developmental_checklist_items.activity_id', 'activities.name as activity_name']);

        return ['itemId' => $i->id, 'item' => $i->item, 'quarter' => $i->quarter_number, 'activityId' => $i->activity_id, 'activityName' => $i->activity_name, 'hasRecords' => $this->checklistItemsWithRecords([$i->id]) !== []];
    }

    // Sets or clears one learner's rating, and optionally its remarks, for one checklist item in one quarter of the section's school year.
    public function saveChecklistRating(SaveChecklistRatingRequest $request, #[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);
        $data = $request->validated();
        $quarterId = DB::table('quarters')->where(['school_year_id' => $section->school_year_id, 'quarter_number' => $data['quarter']])->value('id');
        if (! $quarterId) {
            throw ValidationException::withMessages(['quarter' => 'Set this quarter\'s dates in Manage Quarters first.']);
        }

        $key = ['enrollment_id' => $data['enrollmentId'], 'checklist_item_id' => $data['itemId'], 'quarter_id' => $quarterId];
        // A first rating makes the item protected, so it waits on the lock checklist item edits take.
        DB::transaction(function () use ($key, $data, $teacher, $section) {
            Quarter::lockState();
            // Remarks live on the rating row, so clearing the rating clears its remarks too.
            if ($data['rating'] === null) {
                DB::table('learner_developmental_checklists')->where($key)->delete();
            } else {
                DB::table('learner_developmental_checklists')->updateOrInsert($key, [
                    'teacher_id' => $teacher->id,
                    'rating' => $data['rating'],
                    'assessed_at' => now(),
                    'updated_at' => now(),
                ] + (array_key_exists('remarks', $data) ? ['remarks' => $data['remarks']] : []));
                // A rating can be given before any quarter is active, so a first one starts the year too;
                // "/" (not yet introduced) records that nothing was taught, so it does not.
                if ($data['rating'] !== 'not_yet_observed') {
                    SchoolYear::markStarted($section->school_year_id);
                }
            }
        });
        $remarks = DB::table('learner_developmental_checklists')->where($key)->value('remarks');

        return response()->json(['enrollmentId' => $data['enrollmentId'], 'itemId' => $data['itemId'], 'quarter' => $data['quarter'], 'rating' => $data['rating'], 'remarks' => $remarks]);
    }

    // One board entry per module; MAPEH's lessons from all three of its domains merge into one entry.
    // Lessons are shared curriculum; only scheduleDays belongs to a section, so it comes from
    // ?section= (this teacher's, active school year, else 404). Without it no schedule is sent,
    // never a merge of several sections' timetables.
    public function modules(Request $request, #[CurrentUser] Teacher $teacher): JsonResponse
    {
        $sectionId = $request->query('section');
        $section = $sectionId === null ? null : $teacher->sections()
            ->whereKey(ctype_digit((string) $sectionId) ? (int) $sectionId : 0)
            ->whereHas('schoolYear', fn ($q) => $q->where('status', 'active'))
            ->firstOrFail();

        $modules = DB::table('modules')->where('is_active', true)->orderBy('id')->get(['id', 'name']);

        $lessons = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->where('lessons.is_active', true)
            ->whereNotNull('lessons.week_number')
            ->orderBy('lessons.quarter_number')->orderBy('lessons.week_number')->orderBy('lessons.day_of_week')->orderBy('lessons.sequence_order')
            ->get(['lessons.id', 'module_domains.module_id', 'lessons.module_domain_id', 'lessons.title', 'lessons.description', 'lessons.lesson_video_url', 'lessons.quarter_number', 'lessons.week_number', 'lessons.day_of_week'])
            ->groupBy('module_id');

        $recordedLessons = array_flip($this->lessonsWithRecords($lessons->collapse()->pluck('id')->all()));

        $activities = DB::table('activities')->where('is_active', true)->get(['id', 'lesson_id', 'activity_format'])->groupBy('lesson_id');

        $schedules = $section ? $this->activeSchedulesByModule($section->id) : [];
        $completion = $section ? $this->lessonCompletion($section->id, $lessons->collapse(), $activities) : [];

        $domains = DB::table('module_domains')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where('developmental_domains.is_active', true)
            ->orderBy('developmental_domains.display_order')
            ->get(['module_domains.id', 'module_domains.module_id', 'developmental_domains.name'])
            ->groupBy('module_id');

        return response()->json($modules->map(fn ($m) => [
            'id' => $m->id,
            'moduleName' => $m->name,
            'scheduleDays' => $schedules[$m->id] ?? [],
            'domains' => ($domains[$m->id] ?? collect())->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values(),
            'lessons' => ($lessons[$m->id] ?? collect())
                ->map(fn ($l) => $this->boardLesson($l, $m->id, (int) $l->module_domain_id, count($activities[$l->id] ?? []), isset($recordedLessons[$l->id]), $completion[$l->id] ?? null))->values(),
        ])->values());
    }

    // lesson_id => how far this section got with the lesson card it belongs to. Same-named lessons in one
    // week and domain are one card on the board (groupByTopic), so their activities are required together.
    // Required: every active activity. The status is classroom delivery: done once the teacher has finished
    // presenting every required activity in this section's classes (moving on or ending the class closes it),
    // whatever the learners did, so absent learners never hold it back. The count is learners: the section's
    // active enrollments (absent and late ones included; transferred, withdrawn and completed ones not), and
    // a learner completed the card when every required activity is finished: a digital attempt submitted with
    // any score, or a practical the teacher rated (the Class Scores rule). Sections and enrollments belong to
    // one school year, so other sections' and earlier years' classes and results never count.
    private function lessonCompletion(int $sectionId, Collection $lessons, Collection $activities): array
    {
        $enrollments = DB::table('learner_school_years')->where(['section_id' => $sectionId, 'status' => 'active'])->pluck('id');
        $activityIds = $lessons->flatMap(fn ($l) => ($activities[$l->id] ?? collect())->pluck('id'));

        // One row per learner and activity, however many attempts: did they start it, and did they finish it.
        $work = $enrollments->isEmpty() || $activityIds->isEmpty() ? collect() : DB::table('learner_activity_attempts')
            ->join('learner_activity_assignments', 'learner_activity_assignments.id', '=', 'learner_activity_attempts.assignment_id')
            ->join('activities', 'activities.id', '=', 'learner_activity_assignments.activity_id')
            ->whereIn('learner_activity_assignments.enrollment_id', $enrollments)
            ->whereIn('learner_activity_assignments.activity_id', $activityIds)
            ->groupBy('learner_activity_assignments.enrollment_id', 'learner_activity_assignments.activity_id')
            ->get([
                'learner_activity_assignments.enrollment_id', 'learner_activity_assignments.activity_id',
                DB::raw("bool_or((activities.activity_format = 'digital' and learner_activity_attempts.status in ('evaluated', 'submitted'))
                    or (activities.activity_format = 'practical' and learner_activity_attempts.practical_rating is not null)) as finished"),
            ])
            ->groupBy('activity_id');

        // activity_id => true once presented and closed in this section's classes, false while still on screen.
        $conducted = $activityIds->isEmpty() ? collect() : DB::table('classroom_session_activities')
            ->join('classroom_session_lessons', 'classroom_session_lessons.id', '=', 'classroom_session_activities.session_lesson_id')
            ->join('classroom_sessions', 'classroom_sessions.id', '=', 'classroom_session_lessons.classroom_session_id')
            ->join('section_modules', 'section_modules.id', '=', 'classroom_sessions.section_module_id')
            ->where('section_modules.section_id', $sectionId)
            ->whereIn('classroom_session_activities.activity_id', $activityIds)
            ->whereIn('classroom_session_activities.status', ['active', 'completed'])
            ->groupBy('classroom_session_activities.activity_id')
            ->get(['classroom_session_activities.activity_id', DB::raw("bool_or(classroom_session_activities.status = 'completed') as finished")])
            ->pluck('finished', 'activity_id');

        $total = $enrollments->count();
        $result = [];
        foreach ($lessons->groupBy(fn ($l) => "{$l->module_domain_id}|{$l->quarter_number}|{$l->week_number}|".mb_strtolower(trim($l->title))) as $card) {
            $required = $card->flatMap(fn ($l) => ($activities[$l->id] ?? collect())->pluck('id'))->unique();
            $rows = $required->flatMap(fn ($id) => $work[$id] ?? []);
            $completed = $required->isEmpty() ? 0 : $enrollments
                ->filter(fn ($e) => $rows->where('enrollment_id', $e)->where('finished', true)->count() === $required->count())->count();
            $status = $required->isNotEmpty() && $required->every(fn ($id) => $conducted[$id] ?? false) ? 'done'
                : ($rows->isNotEmpty() || $required->contains(fn ($id) => isset($conducted[$id])) ? 'in_progress' : null);
            foreach ($card as $l) {
                $result[$l->id] = ['status' => $status, 'completed' => $completed, 'total' => $total];
            }
        }

        return $result;
    }

    public function domains(): JsonResponse
    {
        return response()->json(DB::table('developmental_domains')->where('is_active', true)->orderBy('display_order')->get(['id', 'name'])
            ->map(fn ($d) => ['domainId' => $d->id, 'domainName' => $d->name]));
    }

    // Adds a module linked to its domains and to every active-year section; its class time is set later in Class Schedule.
    // Only before the active year has started (SchoolYear::markStarted); with no active year there is none to protect.
    public function storeModule(StoreModuleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $name = trim($data['moduleName']);
        $newDomain = trim($data['newDomain'] ?? '');

        $moduleId = DB::transaction(function () use ($data, $name, $newDomain) {
            // Read under both locks before anything is written: quarter activation and checklist ratings hold the
            // quarter lock, and a first attendance mark or progress report holds the year row while it stamps started_at.
            Quarter::lockState();
            $year = SchoolYear::where('status', 'active')->lockForUpdate()->first();
            if ($year?->started_at) {
                throw ValidationException::withMessages(['schoolYear' => self::MODULE_LOCKED]);
            }

            $domainIds = $data['domainIds'] ?? [];
            if ($newDomain !== '') {
                $domainIds[] = DB::table('developmental_domains')->insertGetId([
                    'code' => self::domainCode($newDomain),
                    'name' => $newDomain,
                    'display_order' => DB::table('developmental_domains')->max('display_order') + 1,
                ]);
            }

            $moduleId = DB::table('modules')->insertGetId([
                'code' => self::moduleCode($name),
                'name' => $name,
                'grade_level' => self::GRADE_LEVEL,
            ]);

            DB::table('module_domains')->insert(array_map(
                fn (int $domainId) => ['module_id' => $moduleId, 'domain_id' => $domainId],
                $domainIds,
            ));

            if ($year) {
                $sections = DB::table('sections')->where(['school_year_id' => $year->id, 'grade_level' => self::GRADE_LEVEL])->get(['id', 'teacher_id']);
                DB::table('section_modules')->insert($sections->map(fn ($s) => [
                    'section_id' => $s->id,
                    'module_id' => $moduleId,
                    'teacher_id' => $s->teacher_id,
                ])->all());
            }

            return $moduleId;
        });

        $domains = DB::table('module_domains')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where('module_domains.module_id', $moduleId)
            ->orderBy('developmental_domains.display_order')
            ->get(['module_domains.id', 'developmental_domains.name']);

        return response()->json([
            'id' => $moduleId,
            'moduleName' => $name,
            'scheduleDays' => [],
            'domains' => $domains->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values(),
            'lessons' => [],
        ], 201);
    }

    public function storeLesson(StoreLessonRequest $request, int $module): JsonResponse
    {
        $data = $request->validated();

        $where = ['module_domain_id' => $data['moduleDomainId'], 'quarter_number' => $data['quarter'], 'week_number' => $data['week']];
        $path = $request->file('video')?->store('lesson-videos', 'public');

        try {
            $id = DB::transaction(function () use ($where, $data, $path) {
                Quarter::lockState();
                $this->ensureQuarterEditable((int) $data['quarter']);

                // New lessons go last within their week.
                return DB::table('lessons')->insertGetId($where + [
                    'title' => trim($data['title']),
                    'day_of_week' => $data['day'],
                    'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                    'sequence_order' => DB::table('lessons')->where($where)->max('sequence_order') + 1,
                    // The column holds the public-disk path; boardLesson turns it into a URL.
                    'lesson_video_url' => $path,
                ]);
            });
        } catch (\Throwable $e) {
            // The lesson never saved, so an uploaded video would be left with nothing pointing at it.
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        $lesson = DB::table('lessons')->where('id', $id)->first(['id', 'title', 'description', 'lesson_video_url', 'quarter_number', 'week_number', 'day_of_week']);

        return response()->json($this->boardLesson($lesson, $module, (int) $data['moduleDomainId'], 0), 201);
    }

    public function updateLesson(UpdateLessonRequest $request, int $lesson): JsonResponse
    {
        $description = filled($request->validated('description')) ? trim($request->validated('description')) : null;
        $changes = ['description' => $description, 'updated_at' => now()];
        if ($request->has('title')) {
            $changes['title'] = trim($request->validated('title'));
        }
        $updated = DB::transaction(function () use ($lesson, $changes) {
            Quarter::lockState();
            // Title and description only: the request has no quarter, so a lesson can't be moved in or out of the active one.
            $this->ensureQuarterEditable($this->lessonQuarter($lesson));
            $this->ensureNoRecords($this->lessonsWithRecords([$lesson]) !== []);

            return DB::table('lessons')->where(['id' => $lesson, 'is_active' => true])->update($changes);
        });
        abort_if(! $updated, 404);

        return response()->json(['lessonId' => $lesson, 'description' => $description, 'title' => $changes['title'] ?? null]);
    }

    // Attaches a video to a lesson that has none; to replace one, remove it first (destroyLessonVideo).
    // Also allowed in the active quarter, where it is the only change a lesson can take (ensureVideoAttachable).
    public function storeLessonVideo(StoreLessonVideoRequest $request, int $lesson): JsonResponse
    {
        $current = DB::table('lessons')->where(['id' => $lesson, 'is_active' => true])->first(['lesson_video_url']);
        abort_if(! $current, 404);
        if ($current->lesson_video_url) {
            throw ValidationException::withMessages(['video' => 'This lesson already has a video.']);
        }

        $path = $this->storeVideo($request, 'lesson-videos');
        try {
            DB::transaction(function () use ($lesson, $path) {
                Quarter::lockState();
                $this->ensureVideoAttachable($this->lessonQuarter($lesson), fn () => $this->lessonsWithRecords([$lesson]) !== []);
                // Only while it still has none, so a second upload racing this one can't overwrite it.
                $saved = DB::table('lessons')->where(['id' => $lesson, 'is_active' => true])->whereNull('lesson_video_url')
                    ->update(['lesson_video_url' => $path, 'updated_at' => now()]);
                if (! $saved) {
                    throw ValidationException::withMessages(['video' => 'This lesson already has a video.']);
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return response()->json(['lessonId' => $lesson, 'videoUrl' => $this->videoUrl($path)]);
    }

    // A teacher-made practical activity: learners watch the demonstration, do it in class, and the teacher rates it.
    public function storePracticalActivity(SavePracticalActivityRequest $request, int $lesson): JsonResponse
    {
        abort_unless(DB::table('lessons')->where(['id' => $lesson, 'is_active' => true])->exists(), 404);
        $data = $request->validated();
        $path = $request->file('video')?->store('practical-demos', 'public');

        try {
            $id = DB::transaction(function () use ($lesson, $data, $path) {
                Quarter::lockState();
                $this->ensureQuarterEditable($this->lessonQuarter($lesson));
                $order = (DB::table('activities')->where('lesson_id', $lesson)->max('sequence_order') ?? 0) + 1;
                $id = DB::table('activities')->insertGetId([
                    'lesson_id' => $lesson,
                    'activity_code' => "PR-L{$lesson}-{$order}",
                    'name' => trim($data['name']),
                    'description' => trim($data['description']),
                    'instructions' => trim($data['instructions']),
                    'activity_format' => 'practical',
                    'sequence_order' => $order,
                ]);
                DB::table('practical_activity_details')->insert([
                    'activity_id' => $id,
                    'demo_video_url' => $path,
                    'teacher_guidelines' => trim($data['teacherGuidelines']),
                ]);

                return $id;
            });
        } catch (\Throwable $e) {
            // The rows never saved, so an uploaded video would be left with nothing pointing at it.
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        return response()->json($this->weekActivity($this->activityRows()->where('activities.id', $id)->first()), 201);
    }

    // Renames a practical activity, rewrites its steps or rating guide, or swaps its demonstration video.
    public function updatePracticalActivity(SavePracticalActivityRequest $request, int $activity): JsonResponse
    {
        $current = $this->activityRows()->where(['activities.id' => $activity, 'activities.activity_format' => 'practical'])->first();
        abort_if(! $current, 404);
        $data = $request->validated();
        $path = $request->file('video')?->store('practical-demos', 'public');

        try {
            DB::transaction(function () use ($activity, $current, $data, $path) {
                Quarter::lockState();
                // The request has no lesson, so the activity stays on its lesson and quarter.
                $this->ensureQuarterEditable($this->lessonQuarter($current->lesson_id));
                // Its name, steps, rating guide and demo are what past ratings were given against.
                $this->ensureNoRecords($this->activitiesWithRecords([$activity]) !== []);
                DB::table('activities')->where('id', $activity)->update(['name' => trim($data['name']), 'description' => trim($data['description']), 'instructions' => trim($data['instructions'])]);
                DB::table('practical_activity_details')->where('activity_id', $activity)->update([
                    'teacher_guidelines' => trim($data['teacherGuidelines']),
                    'updated_at' => now(),
                ] + ($path ? ['demo_video_url' => $path] : []));
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }
        // The old video is only removed once the new one is saved in its place.
        if ($path && $current->demo_video_url) {
            Storage::disk('public')->delete($current->demo_video_url);
        }

        return response()->json($this->weekActivity($this->activityRows()->where('activities.id', $activity)->first()));
    }

    // Attaches the demonstration video to a practical activity that has none. Learners can't start the activity
    // without one (LearnerSessionController::join), so the active quarter allows this, and only this (ensureVideoAttachable).
    public function storePracticalActivityVideo(StoreLessonVideoRequest $request, int $activity): JsonResponse
    {
        $current = $this->activityRows()->where(['activities.id' => $activity, 'activities.activity_format' => 'practical'])->first();
        abort_if(! $current, 404);
        if ($current->demo_video_url) {
            throw ValidationException::withMessages(['video' => 'This practical activity already has a video.']);
        }

        $path = $this->storeVideo($request, 'practical-demos');
        try {
            DB::transaction(function () use ($activity, $current, $path) {
                Quarter::lockState();
                $this->ensureVideoAttachable($this->lessonQuarter($current->lesson_id), fn () => $this->activitiesWithRecords([$activity]) !== []);
                // Only the video column, and only while it is still empty: nothing else about the activity changes.
                $saved = DB::table('practical_activity_details')->where('activity_id', $activity)->whereNull('demo_video_url')
                    ->update(['demo_video_url' => $path, 'updated_at' => now()]);
                if (! $saved) {
                    throw ValidationException::withMessages(['video' => 'This practical activity already has a video.']);
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return response()->json($this->weekActivity($this->activityRows()->where('activities.id', $activity)->first()));
    }

    // Stores an uploaded video on the public disk, failing the request unless the file is really there.
    private function storeVideo(StoreLessonVideoRequest $request, string $folder): string
    {
        $path = $request->file('video')->store($folder, 'public');
        if (! $path || ! Storage::disk('public')->exists($path)) {
            throw ValidationException::withMessages(['video' => 'The video could not be saved. Please try again.']);
        }

        return $path;
    }

    // Hides a practical activity everywhere new work starts; past results stay, and skills linked to it are unlinked.
    public function destroyPracticalActivity(int $activity): JsonResponse
    {
        abort_unless($this->activityRows()->where(['activities.id' => $activity, 'activities.activity_format' => 'practical'])->exists(), 404);
        if (DB::table('classroom_session_activities')->where(['activity_id' => $activity, 'status' => 'active'])->exists()) {
            throw ValidationException::withMessages(['activity' => 'This activity is running in a class right now. End the class first.']);
        }

        DB::transaction(function () use ($activity) {
            Quarter::lockState();
            $this->ensureQuarterEditable($this->lessonQuarter(DB::table('activities')->where('id', $activity)->value('lesson_id')));
            $this->ensureNoRecords($this->activitiesWithRecords([$activity]) !== []);
            DB::table('activities')->where('id', $activity)->update(['is_active' => false]);
            DB::table('developmental_checklist_items')->where('activity_id', $activity)->update(['activity_id' => null, 'updated_at' => now()]);
        });

        return response()->json(['id' => $activity]);
    }

    // Active activities with their practical details, the rows behind the week view's activity cards.
    private function activityRows(): Builder
    {
        return DB::table('activities')
            ->leftJoin('practical_activity_details', 'practical_activity_details.activity_id', '=', 'activities.id')
            ->where('activities.is_active', true)
            ->orderBy('activities.sequence_order')
            ->select(['activities.id', 'activities.lesson_id', 'activities.name', 'activities.description', 'activities.instructions', 'activities.activity_format', 'practical_activity_details.teacher_guidelines', 'practical_activity_details.demo_video_url']);
    }

    // One activity card; practical ones carry what the edit form needs.
    private function weekActivity(object $a, bool $hasRecords = false): array
    {
        return [
            'id' => $a->id,
            'activityName' => $a->name,
            'description' => $a->description,
            'format' => $a->activity_format,
            'hasRecords' => $hasRecords,
        ] + ($a->activity_format === 'practical' ? [
            'instructions' => $a->instructions,
            'teacherGuidelines' => $a->teacher_guidelines,
            'demoVideoUrl' => $this->videoUrl($a->demo_video_url),
            // Saved and on disk: what learners need before they can start it (ClassSession::demoVideoReady).
            'demoVideoReady' => ClassSession::demoVideoReady($a),
        ] : []);
    }

    // Removes a lesson's video and its file, so the teacher can upload a new one.
    public function destroyLessonVideo(int $lesson): JsonResponse
    {
        $current = DB::table('lessons')->where(['id' => $lesson, 'is_active' => true])->first(['lesson_video_url']);
        abort_if(! $current, 404);

        DB::transaction(function () use ($lesson, $current) {
            Quarter::lockState();
            $this->ensureQuarterEditable($this->lessonQuarter($lesson));
            $this->ensureNoRecords($this->lessonsWithRecords([$lesson]) !== []);
            if ($current->lesson_video_url) {
                DB::table('lessons')->where('id', $lesson)->update(['lesson_video_url' => null, 'updated_at' => now()]);
            }
        });
        // Only once the row no longer points at it.
        if ($current->lesson_video_url) {
            Storage::disk('public')->delete($current->lesson_video_url);
        }

        return response()->json(['lessonId' => $lesson, 'videoUrl' => null]);
    }

    private function boardLesson(object $l, int $moduleId, int $domainId, int $activityCount, bool $hasRecords = false, ?array $completion = null): array
    {
        return [
            'id' => $l->id,
            'lessonName' => $l->title,
            'description' => $l->description,
            'videoUrl' => $this->videoUrl($l->lesson_video_url),
            'moduleId' => $moduleId,
            'domainId' => $domainId,
            'quarterId' => $l->quarter_number,
            'quarterName' => $this->quarterName($l->quarter_number),
            'week' => $l->week_number,
            'dayOfWeek' => $l->day_of_week,
            'activityCount' => $activityCount,
            // Used in a class or by a learner: viewable, never changed again (see ensureNoRecords).
            'hasRecords' => $hasRecords,
            // The selected section's progress on this lesson's card (see lessonCompletion); null without ?section=.
            'completion' => $completion,
        ];
    }

    // Built from the request host, so the link works whichever address the app was opened on.
    private function videoUrl(?string $path): ?string
    {
        return $path ? asset('storage/'.$path) : null;
    }

    public function weekLessons(Request $request, int $module): JsonResponse
    {
        $data = $request->validate([
            'quarter' => ['required', 'integer', 'between:1,4'],
            'week' => ['required', 'integer', 'min:1'],
        ]);

        $lessons = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->where(['module_domains.module_id' => $module, 'lessons.quarter_number' => $data['quarter'], 'lessons.week_number' => $data['week'], 'lessons.is_active' => true])
            ->orderBy('lessons.day_of_week')->orderBy('lessons.sequence_order')
            ->get(['lessons.id', 'lessons.title', 'lessons.day_of_week']);

        $activities = $this->activityRows()->whereIn('activities.lesson_id', $lessons->pluck('id'))->get()->groupBy('lesson_id');
        $recordedLessons = array_flip($this->lessonsWithRecords($lessons->pluck('id')->all()));
        $recordedActivities = array_flip($this->activitiesWithRecords($activities->collapse()->pluck('id')->all()));

        return response()->json($lessons->map(fn ($l) => [
            'lessonId' => $l->id,
            'lessonName' => $l->title,
            'dayOfWeek' => $l->day_of_week,
            'hasRecords' => isset($recordedLessons[$l->id]),
            'activities' => ($activities[$l->id] ?? collect())->map(fn ($a) => $this->weekActivity($a, isset($recordedActivities[$a->id])))->values(),
        ]));
    }

    // module_id => weekdays and times from one section's timetable valid today (the caller
    // checks ownership). A module whose assignment or schedule doesn't apply just gets no
    // schedule; it stays on the board. One active schedule per module means each weekday
    // appears once.
    private function activeSchedulesByModule(int $sectionId): array
    {
        $rows = $this->usableSchedules($sectionId)
            ->join('schedule_days', 'schedule_days.schedule_id', '=', 'class_schedules.id')
            ->orderBy('schedule_days.day_of_week')
            ->get(['section_modules.module_id', 'schedule_days.day_of_week', 'class_schedules.start_time', 'class_schedules.end_time']);

        $byModule = [];
        foreach ($rows as $row) {
            $byModule[$row->module_id][] = [
                'day' => self::DAY_NAMES[$row->day_of_week],
                'startTime' => date('g:i A', strtotime($row->start_time)),
                'endTime' => date('g:i A', strtotime($row->end_time)),
            ];
        }

        return $byModule;
    }

    // Teachers prepare lessons and practical activities only in an upcoming quarter of the active school year.
    // The active quarter is in use by classes and a completed one is done, so both are read-only, and so is a
    // quarter the active year hasn't set up (or any quarter when no year is active). Pass every quarter a change
    // touches (both ends of a move); null (a missing lesson) is left to the caller's 404. Call inside a
    // transaction after Quarter::lockState(), so an activation can't land before the write.
    private function ensureQuarterEditable(?int ...$quarters): void
    {
        $statuses = Quarter::statuses();
        foreach (array_filter($quarters, fn (?int $quarter) => $quarter !== null) as $quarter) {
            $message = match ($statuses[$quarter] ?? null) {
                'upcoming' => null,
                'active' => "The {$this->quarterName($quarter)} is active and open for classes, so its lessons and practical activities are read-only.",
                'completed' => "The {$this->quarterName($quarter)} is completed, so its lessons and practical activities can no longer be changed.",
                default => "The {$this->quarterName($quarter)} isn't set up in the active school year, so its lessons and practical activities can't be changed yet. Set its dates in Manage Quarters first.",
            };
            if ($message) {
                throw ValidationException::withMessages(['quarter' => $message]);
            }
        }
    }

    // Attaching a first video. The active quarter allows this (and no other change): it only adds what learners
    // watch and changes no result, so a lesson already taught can still get one. Every other quarter keeps the usual
    // edit rules. Call inside a transaction after Quarter::lockState(), like ensureQuarterEditable.
    private function ensureVideoAttachable(?int $quarter, \Closure $hasRecords): void
    {
        if ($quarter !== null && (Quarter::statuses()[$quarter] ?? null) === 'active') {
            return;
        }
        $this->ensureQuarterEditable($quarter);
        $this->ensureNoRecords($hasRecords());
    }

    // Content used in a class or by a learner stays as it was, whichever quarter is active now: changing it
    // would change what existing results mean. Checked after ensureQuarterEditable, so the active quarter's
    // reason wins when both apply. Call under Quarter::lockState(), the lock class start also takes before
    // it records the first use.
    private function ensureNoRecords(bool $hasRecords): void
    {
        if ($hasRecords) {
            throw ValidationException::withMessages(['protected' => 'This content has classroom or learner records and can no longer be changed.']);
        }
    }

    // A skill linked to a game takes that game's results as evidence (checklist page, ProgressReportDraft::build),
    // so linking one to a game learners already played would reinterpret those past results.
    private function ensureLinkable(?int $activity): void
    {
        if ($activity && $this->activitiesWithRecords([$activity]) !== []) {
            throw ValidationException::withMessages(['activityId' => 'This activity has classroom or learner records, so linking a skill to it would change past results and reports. Leave the skill unlinked or pick an activity without records.']);
        }
    }

    // Of these activities, the ones put on screen in a class or assigned to a learner, in any section or school year.
    // Attempts, scores and practical ratings all hang off an assignment, so they are covered too.
    private function activitiesWithRecords(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('classroom_session_activities')->whereIn('activity_id', $ids)->select('activity_id')
            ->union(DB::table('learner_activity_assignments')->whereIn('activity_id', $ids)->select('activity_id'))
            ->pluck('activity_id')->map(fn ($id) => (int) $id)->all();
    }

    // Of these lessons, the ones taught in a class, or with any activity (even a removed one) that has records.
    private function lessonsWithRecords(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $usedActivities = DB::table('classroom_session_activities')->select('activity_id')
            ->union(DB::table('learner_activity_assignments')->select('activity_id'));

        return DB::table('classroom_session_lessons')->whereIn('lesson_id', $ids)->select('lesson_id')
            ->union(DB::table('activities')->whereIn('lesson_id', $ids)->whereIn('id', $usedActivities)->select('lesson_id'))
            ->pluck('lesson_id')->map(fn ($id) => (int) $id)->all();
    }

    // Of these checklist items, the ones a learner was rated on, or linked to a game with records.
    private function checklistItemsWithRecords(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $usedActivities = DB::table('classroom_session_activities')->select('activity_id')
            ->union(DB::table('learner_activity_assignments')->select('activity_id'));

        // "/" (not_yet_observed, Not yet introduced) records only that the skill was not taught yet, so it locks nothing.
        return DB::table('learner_developmental_checklists')->whereIn('checklist_item_id', $ids)->where('rating', '!=', 'not_yet_observed')->select('checklist_item_id as id')
            ->union(DB::table('developmental_checklist_items')->whereIn('id', $ids)->whereIn('activity_id', $usedActivities)->select('id'))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    // Read fresh under the lock; null for a missing lesson, which the caller's 404 handles.
    private function lessonQuarter(?int $lesson): ?int
    {
        $quarter = $lesson ? DB::table('lessons')->where('id', $lesson)->value('quarter_number') : null;

        return $quarter === null ? null : (int) $quarter;
    }

    private function quarterName(int $number): string
    {
        return [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'][$number].' Quarter';
    }
}

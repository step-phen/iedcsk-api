<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveChecklistRatingRequest;
use App\Http\Requests\SaveChecklistItemRequest;
use App\Http\Requests\StoreLessonRequest;
use App\Http\Requests\StoreLessonVideoRequest;
use App\Http\Requests\SavePracticalActivityRequest;
use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateLessonRequest;
use App\Models\SchoolYear;
use App\Models\Teacher;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CurriculumController extends Controller
{
    // The only grade level the app supports.
    public const GRADE_LEVEL = 'Kinder 1';

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

        // Each linked game's latest result, so the teacher rates the skill from what the learner actually did.
        $results = ClassroomRecordController::latestResults($learners->pluck('enrollment_id'), $items->collapse()->pluck('activity_id')->filter()->unique()->values());

        return response()->json([
            'quarters' => array_map(fn (int $n) => ['quarter' => $n, 'quarterName' => $this->quarterName($n)], [1, 2, 3, 4]),
            'domains' => DB::table('developmental_domains')->where('is_active', true)->orderBy('display_order')->get(['id', 'name'])
                ->map(fn ($d) => [
                    'domainId' => $d->id,
                    'domainName' => $d->name,
                    'items' => ($items[$d->id] ?? collect())->map(fn ($i) => ['itemId' => $i->id, 'item' => $i->item, 'quarter' => $i->quarter_number, 'activityId' => $i->activity_id, 'activityName' => $i->activity_name])->values(),
                    'activities' => ($activities[$d->id] ?? collect())->map(fn ($a) => ['activityId' => $a->id, 'name' => $a->name, 'quarter' => $a->quarter_number])->values(),
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
        $id = DB::table('developmental_checklist_items')->insertGetId($where + [
            'item' => trim($data['item']),
            'activity_id' => $data['activityId'] ?? null,
            'display_order' => (DB::table('developmental_checklist_items')->where($where)->max('display_order') ?? 0) + 1,
        ]);

        return response()->json($this->checklistItem($id), 201);
    }

    // Renames an item or relinks its game; its domain and quarter stay, so its ratings stay with it.
    public function updateChecklistItem(SaveChecklistItemRequest $request, int $item): JsonResponse
    {
        $data = $request->validated();
        DB::table('developmental_checklist_items')->where('id', $item)->update([
            'item' => trim($data['item']),
            'activity_id' => $data['activityId'] ?? null,
            'updated_at' => now(),
        ]);

        return response()->json($this->checklistItem($item));
    }

    private function checklistItem(int $id): array
    {
        $i = DB::table('developmental_checklist_items')
            ->leftJoin('activities', 'activities.id', '=', 'developmental_checklist_items.activity_id')
            ->where('developmental_checklist_items.id', $id)
            ->first(['developmental_checklist_items.id', 'developmental_checklist_items.item', 'developmental_checklist_items.quarter_number', 'developmental_checklist_items.activity_id', 'activities.name as activity_name']);

        return ['itemId' => $i->id, 'item' => $i->item, 'quarter' => $i->quarter_number, 'activityId' => $i->activity_id, 'activityName' => $i->activity_name];
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
        }
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

        $activityCounts = DB::table('activities')->where('is_active', true)
            ->groupBy('lesson_id')->selectRaw('lesson_id, count(*) as total')->pluck('total', 'lesson_id');

        $schedules = $section ? $this->activeSchedulesByModule($section->id) : [];

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
                ->map(fn ($l) => $this->boardLesson($l, $m->id, (int) $l->module_domain_id, (int) ($activityCounts[$l->id] ?? 0)))->values(),
        ])->values());
    }

    public function domains(): JsonResponse
    {
        return response()->json(DB::table('developmental_domains')->where('is_active', true)->orderBy('display_order')->get(['id', 'name'])
            ->map(fn ($d) => ['domainId' => $d->id, 'domainName' => $d->name]));
    }

    // Adds a module linked to its domains and to every active-year section; its class time is set later in Class Schedule.
    public function storeModule(StoreModuleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $name = trim($data['moduleName']);
        $newDomain = trim($data['newDomain'] ?? '');
        $year = SchoolYear::active();

        $moduleId = DB::transaction(function () use ($data, $name, $newDomain, $year) {
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
        // New lessons go last within their week.
        $id = DB::table('lessons')->insertGetId($where + [
            'title' => trim($data['title']),
            'day_of_week' => $data['day'],
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'sequence_order' => DB::table('lessons')->where($where)->max('sequence_order') + 1,
            // The column holds the public-disk path; boardLesson turns it into a URL.
            'lesson_video_url' => $request->file('video')?->store('lesson-videos', 'public'),
        ]);

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
        $updated = DB::table('lessons')->where(['id' => $lesson, 'is_active' => true])->update($changes);
        abort_if(! $updated, 404);

        return response()->json(['lessonId' => $lesson, 'description' => $description, 'title' => $changes['title'] ?? null]);
    }

    // Attaches a video to a lesson that has none; to replace one, remove it first (destroyLessonVideo).
    public function storeLessonVideo(StoreLessonVideoRequest $request, int $lesson): JsonResponse
    {
        $current = DB::table('lessons')->where(['id' => $lesson, 'is_active' => true])->first(['lesson_video_url']);
        abort_if(! $current, 404);
        if ($current->lesson_video_url) {
            throw ValidationException::withMessages(['video' => 'This lesson already has a video.']);
        }

        $path = $request->file('video')->store('lesson-videos', 'public');
        DB::table('lessons')->where('id', $lesson)->update(['lesson_video_url' => $path, 'updated_at' => now()]);

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
            DB::transaction(function () use ($activity, $data, $path) {
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

    // Hides a practical activity everywhere new work starts; past results stay, and skills linked to it are unlinked.
    public function destroyPracticalActivity(int $activity): JsonResponse
    {
        abort_unless($this->activityRows()->where(['activities.id' => $activity, 'activities.activity_format' => 'practical'])->exists(), 404);
        if (DB::table('classroom_session_activities')->where(['activity_id' => $activity, 'status' => 'active'])->exists()) {
            throw ValidationException::withMessages(['activity' => 'This activity is running in a class right now. End the class first.']);
        }

        DB::transaction(function () use ($activity) {
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
    private function weekActivity(object $a): array
    {
        return [
            'id' => $a->id,
            'activityName' => $a->name,
            'description' => $a->description,
            'format' => $a->activity_format,
        ] + ($a->activity_format === 'practical' ? [
            'instructions' => $a->instructions,
            'teacherGuidelines' => $a->teacher_guidelines,
            'demoVideoUrl' => $this->videoUrl($a->demo_video_url),
        ] : []);
    }

    // Removes a lesson's video and its file, so the teacher can upload a new one.
    public function destroyLessonVideo(int $lesson): JsonResponse
    {
        $current = DB::table('lessons')->where(['id' => $lesson, 'is_active' => true])->first(['lesson_video_url']);
        abort_if(! $current, 404);

        if ($current->lesson_video_url) {
            DB::table('lessons')->where('id', $lesson)->update(['lesson_video_url' => null, 'updated_at' => now()]);
            Storage::disk('public')->delete($current->lesson_video_url);
        }

        return response()->json(['lessonId' => $lesson, 'videoUrl' => null]);
    }

    private function boardLesson(object $l, int $moduleId, int $domainId, int $activityCount): array
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

        return response()->json($lessons->map(fn ($l) => [
            'lessonId' => $l->id,
            'lessonName' => $l->title,
            'dayOfWeek' => $l->day_of_week,
            'activities' => ($activities[$l->id] ?? collect())->map(fn ($a) => $this->weekActivity($a))->values(),
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

    private function quarterName(int $number): string
    {
        return [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'][$number].' Quarter';
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSectionRequest;
use App\Models\Section;
use App\Models\Teacher;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SectionController extends Controller
{
    public function index(#[CurrentUser] Teacher $teacher): JsonResponse
    {
        // Sections are created only when a school year is activated (Section::setUp); only an unused one's name can change.
        // Joy, then Faith, then any section without a stored choice; the first is the default.
        $order = array_flip(array_keys(Section::CHOICES));
        $sections = $teacher->sections()
            ->whereHas('schoolYear', fn ($q) => $q->where('status', 'active'))
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Section $s) => $order[$s->choice ?? ''] ?? count($order))
            ->values();
        $recorded = array_flip(Section::withRecords($sections->pluck('id')->all()));

        return response()->json($sections->map(fn (Section $s) => $s->toApiArray(isset($recorded[$s->id]))));
    }

    // Renames one of this teacher's sections in the active school year while it has no learner or classroom records.
    // Only the display name changes: the id, Joy/Faith identity, session, timetable and module links stay.
    public function update(UpdateSectionRequest $request, #[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->whereHas('schoolYear', fn ($q) => $q->where('status', 'active'))->findOrFail($section);
        $name = $request->validated('sectionName');

        $section = DB::transaction(function () use ($section, $name) {
            // The year's sections, locked: two renames can't take the same name, and enrolling a learner or starting a
            // class (both lock their section FOR SHARE) either finishes first and is seen below, or waits for this.
            $siblings = Section::where(['school_year_id' => $section->school_year_id, 'grade_level' => $section->grade_level])
                ->orderBy('id')->lockForUpdate()->get();
            $section = $siblings->firstWhere('id', $section->id);

            // Checked again here, so a form opened before a learner was enrolled can't rename the section.
            if (Section::withRecords([$section->id])) {
                throw ValidationException::withMessages(['section' => Section::NAME_LOCKED_MESSAGE]);
            }
            // Learners are enrolled by section name, so two sections can't share one, whatever the case or spacing.
            $key = fn (string $n) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($n)));
            if ($siblings->contains(fn (Section $s) => $s->id !== $section->id && $key($s->name) === $key($name))) {
                throw ValidationException::withMessages(['sectionName' => 'Another section this school year already has that name.']);
            }

            $section->update(['name' => $name]);

            return $section;
        });

        return response()->json($section->toApiArray(false));
    }

    // The section's editable timetable: each active module with its active schedule's
    // times ("13:00") and ISO weekdays, or null times and no days when it has none.
    public function schedule(#[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);

        return response()->json($this->scheduleRows($section->id));
    }

    // Saves new times/days per module. A changed module's old schedule is deactivated
    // (kept for classroom_sessions history) and a new one inserted for the school year;
    // unchanged modules are left alone.
    public function updateSchedule(Request $request, #[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->with('schoolYear')->findOrFail($section);
        $current = collect($this->scheduleRows($section->id))->keyBy('sectionModuleId');

        $data = $request->validate([
            'modules' => ['required', 'array', 'min:1'],
            'modules.*.sectionModuleId' => ['required', 'integer', 'distinct', Rule::in($current->keys()->all())],
            'modules.*.startTime' => ['nullable', 'date_format:H:i'],
            'modules.*.endTime' => ['nullable', 'date_format:H:i'],
            'modules.*.days' => ['nullable', 'array'],
            'modules.*.days.*' => ['integer', 'between:1,5'],
        ]);

        // A blank row for a never-scheduled module is skipped, keeping indexes so errors land on their own row.
        $modules = [];
        foreach ($data['modules'] as $i => $m) {
            $blank = empty($m['startTime']) && empty($m['endTime']) && empty($m['days']);
            if ($blank && ! $current[$m['sectionModuleId']]['scheduleId']) {
                continue;
            }
            $missing = array_filter([
                "modules.$i.startTime" => empty($m['startTime']) ? 'Set a start time.' : null,
                "modules.$i.endTime" => empty($m['endTime']) ? 'Set an end time.' : null,
                "modules.$i.days" => empty($m['days']) ? 'Pick at least one day.' : null,
            ]);
            if ($missing) {
                throw ValidationException::withMessages($missing);
            }
            $modules[$i] = $m;
        }

        foreach ($modules as $i => $m) {
            if ($m['endTime'] <= $m['startTime']) {
                throw ValidationException::withMessages(["modules.$i.endTime" => 'End time must be after the start time.']);
            }
            // ponytail: O(n²) pair scan, fine for a handful of modules per section.
            foreach ($modules as $j => $other) {
                if ($j > $i && array_intersect($m['days'], $other['days'])
                    && $m['startTime'] < $other['endTime'] && $other['startTime'] < $m['endTime']) {
                    $names = $current[$m['sectionModuleId']]['moduleName'].' and '.$current[$other['sectionModuleId']]['moduleName'];
                    throw ValidationException::withMessages(["modules.$j.startTime" => "$names overlap on the same day."]);
                }
            }
        }

        DB::transaction(function () use ($modules, $current, $section) {
            foreach ($modules as $m) {
                $old = $current[$m['sectionModuleId']];
                $days = array_values(array_unique(array_map('intval', $m['days'])));
                sort($days);
                if ($old['scheduleId'] && $old['startTime'] === $m['startTime'] && $old['endTime'] === $m['endTime'] && $old['days'] === $days) {
                    continue;
                }

                if ($old['scheduleId']) {
                    DB::table('class_schedules')->where('id', $old['scheduleId'])->update(['is_active' => false, 'updated_at' => now()]);
                }
                $scheduleId = DB::table('class_schedules')->insertGetId([
                    'section_module_id' => $m['sectionModuleId'],
                    'start_time' => $m['startTime'],
                    'end_time' => $m['endTime'],
                    'effective_from' => $section->schoolYear->start_date->toDateString(),
                    'effective_until' => $section->schoolYear->end_date->toDateString(),
                ]);
                DB::table('schedule_days')->insert(array_map(
                    fn (int $day) => ['schedule_id' => $scheduleId, 'day_of_week' => $day],
                    $days,
                ));
            }
        });

        return response()->json($this->scheduleRows($section->id));
    }

    private function scheduleRows(int $sectionId): array
    {
        $rows = DB::table('section_modules')
            ->join('modules', 'modules.id', '=', 'section_modules.module_id')
            ->leftJoin('class_schedules', fn ($j) => $j->on('class_schedules.section_module_id', '=', 'section_modules.id')
                ->where('class_schedules.is_active', true))
            ->where('section_modules.section_id', $sectionId)
            ->where('section_modules.is_active', true)
            ->orderBy('modules.id')
            ->get(['section_modules.id as section_module_id', 'modules.name as module_name', 'class_schedules.id as schedule_id', 'start_time', 'end_time']);

        $daysBySchedule = DB::table('schedule_days')
            ->whereIn('schedule_id', $rows->pluck('schedule_id')->filter())
            ->orderBy('day_of_week')
            ->get(['schedule_id', 'day_of_week'])
            ->groupBy('schedule_id');

        return $rows->map(fn ($r) => [
            'sectionModuleId' => $r->section_module_id,
            'moduleName' => $r->module_name,
            'scheduleId' => $r->schedule_id,
            'startTime' => $r->start_time ? substr($r->start_time, 0, 5) : null,
            'endTime' => $r->end_time ? substr($r->end_time, 0, 5) : null,
            'days' => ($daysBySchedule[$r->schedule_id] ?? collect())->pluck('day_of_week')->map(fn ($d) => (int) $d)->all(),
        ])->all();
    }

    // The section's currently-ongoing class, if any: which module is in
    // session and which activity within it is active right now.
    public function activeSession(#[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);

        $session = DB::table('classroom_sessions')
            ->join('section_modules', 'section_modules.id', '=', 'classroom_sessions.section_module_id')
            ->join('modules', 'modules.id', '=', 'section_modules.module_id')
            ->where('section_modules.section_id', $section->id)
            ->where('classroom_sessions.status', 'ongoing')
            ->orderByDesc('classroom_sessions.started_at')
            ->first(['classroom_sessions.id', 'modules.name as module_name']);

        $activityName = $session
            ? DB::table('classroom_session_activities')
                ->join('classroom_session_lessons', 'classroom_session_lessons.id', '=', 'classroom_session_activities.session_lesson_id')
                ->join('activities', 'activities.id', '=', 'classroom_session_activities.activity_id')
                ->where('classroom_session_lessons.classroom_session_id', $session->id)
                ->where('classroom_session_activities.status', 'active')
                ->value('activities.name')
            : null;

        return response()->json([
            'activeModuleName' => $session->module_name ?? null,
            'activeActivityName' => $activityName,
        ]);
    }

    // The school year's active quarter, plus how much of this section's
    // work for that quarter is done: completed lessons (classroom_session_lessons)
    // out of all active lessons targeting that quarter across the section's
    // own modules. No active quarter yet returns null — "No quarters yet".
    public function overallProgress(#[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);

        $quarter = DB::table('quarters')
            ->where('school_year_id', $section->school_year_id)
            ->where('status', 'active')
            ->first();

        if (! $quarter) {
            // response()->json(null) turns into "{}" (Symfony coerces the
            // null to an empty ArrayObject) — fromJsonString writes the
            // literal body instead, so `currentQuarter === null` actually fires.
            return JsonResponse::fromJsonString('null');
        }

        $sectionModules = DB::table('section_modules')->where('section_id', $section->id)->get(['id', 'module_id']);

        $totalLessons = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->whereIn('module_domains.module_id', $sectionModules->pluck('module_id'))
            ->where('lessons.is_active', true)
            ->where('lessons.quarter_number', $quarter->quarter_number)
            ->count();

        $completedLessons = $totalLessons === 0 ? 0 : DB::table('classroom_session_lessons')
            ->join('classroom_sessions', 'classroom_sessions.id', '=', 'classroom_session_lessons.classroom_session_id')
            ->join('lessons', 'lessons.id', '=', 'classroom_session_lessons.lesson_id')
            ->whereIn('classroom_sessions.section_module_id', $sectionModules->pluck('id'))
            ->where('classroom_session_lessons.status', 'completed')
            ->where('lessons.quarter_number', $quarter->quarter_number)
            ->distinct()
            ->count('lessons.id');

        return response()->json([
            'quarterId' => $quarter->id,
            'quarterName' => $quarter->name,
            // The dashboard counts "Week N" from this date.
            'startDate' => $quarter->start_date,
            'percent' => $totalLessons > 0 ? (int) round($completedLessons / $totalLessons * 100) : 0,
        ]);
    }

    // Today's class times (from this section's own timetable) and lessons
    // (from the active quarter only) — both scoped to today's actual weekday,
    // Asia/Manila, never the server's own timezone.
    public function todaysSchedule(#[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);
        $today = Carbon::now('Asia/Manila')->isoWeekday(); // 1 (Mon) .. 7 (Sun), matches schedule_days/lessons.day_of_week

        $sectionModules = DB::table('section_modules')
            ->join('modules', 'modules.id', '=', 'section_modules.module_id')
            ->where('section_modules.section_id', $section->id)
            ->get(['section_modules.id as section_module_id', 'modules.id as module_id', 'modules.name as module_name']);

        $moduleTimes = $this->usableSchedules($section->id)
            ->join('schedule_days', 'schedule_days.schedule_id', '=', 'class_schedules.id')
            ->where('schedule_days.day_of_week', $today)
            ->get(['class_schedules.section_module_id', 'class_schedules.start_time', 'class_schedules.end_time']);

        $moduleIdBySectionModule = $sectionModules->pluck('module_id', 'section_module_id');
        $moduleNameByModule = $sectionModules->pluck('module_name', 'module_id');

        $quarter = DB::table('quarters')
            ->where('school_year_id', $section->school_year_id)
            ->where('status', 'active')
            ->first();

        // No active quarter means nothing counts as "this quarter's" lesson yet.
        // Module code and domain name let the dashboard tell MAPEH's three domains apart.
        $lessons = $quarter ? DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->whereIn('module_domains.module_id', $sectionModules->pluck('module_id'))
            ->where('lessons.is_active', true)
            ->where('lessons.day_of_week', $today)
            ->where('lessons.quarter_number', $quarter->quarter_number)
            ->orderBy('lessons.sequence_order')
            ->get(['lessons.id', 'lessons.title', 'lessons.week_number', 'module_domains.module_id', 'modules.code as module_code', 'developmental_domains.name as domain_name']) : collect();

        $activities = DB::table('activities')
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->where('is_active', true)
            ->orderBy('sequence_order')
            ->get(['id', 'lesson_id', 'name'])
            ->groupBy('lesson_id');

        return response()->json([
            'lessons' => $lessons->map(fn ($l) => [
                'lessonId' => $l->id,
                'lessonName' => $l->title,
                'week' => $l->week_number,
                'moduleId' => $l->module_id,
                'moduleName' => $moduleNameByModule[$l->module_id] ?? null,
                'moduleCode' => $l->module_code,
                'domainName' => $l->domain_name,
                'activities' => ($activities[$l->id] ?? collect())->map(fn ($a) => [
                    'activityId' => $a->id,
                    'activityName' => $a->name,
                ])->values(),
            ])->values(),
            'moduleTimes' => $moduleTimes->map(fn ($row) => [
                'moduleId' => $moduleIdBySectionModule[$row->section_module_id],
                'startTime' => date('g:i A', strtotime($row->start_time)),
                'endTime' => date('g:i A', strtotime($row->end_time)),
            ])->values(),
        ]);
    }

    // Lesson/activity counts for the modules actually assigned to this
    // section (section_modules) — not every module in the catalog.
    public function curriculumResources(#[CurrentUser] Teacher $teacher, int $section): JsonResponse
    {
        $section = $teacher->sections()->findOrFail($section);

        $moduleIds = DB::table('section_modules')->where('section_id', $section->id)->pluck('module_id');

        $lessonCount = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->whereIn('module_domains.module_id', $moduleIds)
            ->where('lessons.is_active', true)
            ->count();

        $activityCount = DB::table('activities')
            ->join('lessons', 'lessons.id', '=', 'activities.lesson_id')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->whereIn('module_domains.module_id', $moduleIds)
            ->where('activities.is_active', true)
            ->where('lessons.is_active', true)
            ->count();

        return response()->json(['lessonCount' => $lessonCount, 'activityCount' => $activityCount]);
    }
}

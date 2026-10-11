<?php

namespace Tests\Feature;

use App\Models\SchoolYear;
use App\Models\Teacher;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SectionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_needs_an_active_school_year(): void
    {
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);

        $this->actingAs($tina)->postJson('/api/sections', ['gradeLevel' => 'Kinder 1', 'sectionName' => 'Joy'])
            ->assertStatus(422)->assertJsonValidationErrors('sectionName');
    }

    public function test_creates_section_with_modules_and_timetable_scoped_to_teacher(): void
    {
        $this->seed(CurriculumSeeder::class);
        SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);

        $this->getJson('/api/sections')->assertUnauthorized();

        $this->actingAs($tina)->postJson('/api/sections', ['gradeLevel' => 'Kinder 1', 'sectionName' => 'Joy'])
            ->assertCreated()
            ->assertJson(['gradeLevel' => 'Kinder 1', 'sectionName' => 'Joy']);

        // Section names are unique per school year and grade, across teachers.
        $this->actingAs($omar)->postJson('/api/sections', ['gradeLevel' => 'Kinder 1', 'sectionName' => 'Joy'])
            ->assertStatus(422)->assertJsonValidationErrors('sectionName');
        $this->actingAs($tina)->postJson('/api/sections', ['gradeLevel' => 'Grade 9', 'sectionName' => 'Hope'])
            ->assertStatus(422)->assertJsonValidationErrors('gradeLevel');

        $this->actingAs($tina)->getJson('/api/sections')->assertOk()->assertJsonCount(1);
        $this->actingAs($omar)->getJson('/api/sections')->assertOk()->assertJsonCount(0);

        $this->assertSame(5, DB::table('section_modules')->count());
        $this->assertSame(5, DB::table('class_schedules')->count());
        // Reading, Language, Writing on M/W/F plus MAPEH, Math on T/Th.
        $this->assertSame(13, DB::table('schedule_days')->count());
        $this->assertSame(['09:15:00', '10:00:00'], array_values((array) DB::table('class_schedules')
            ->join('section_modules', 'section_modules.id', '=', 'class_schedules.section_module_id')
            ->join('modules', 'modules.id', '=', 'section_modules.module_id')
            ->where('modules.code', 'language')->first(['start_time', 'end_time'])));
    }

    public function test_afternoon_section_gets_the_afternoon_timetable(): void
    {
        $this->seed(CurriculumSeeder::class);
        SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);

        $this->actingAs($tina)->postJson('/api/sections', ['gradeLevel' => 'Kinder 1', 'sectionName' => 'Hope', 'session' => 'evening'])
            ->assertStatus(422)->assertJsonValidationErrors('session');
        $this->actingAs($tina)->postJson('/api/sections', ['gradeLevel' => 'Kinder 1', 'sectionName' => 'Hope', 'session' => 'afternoon'])
            ->assertCreated();

        $this->assertSame(13, DB::table('schedule_days')->count());
        $this->assertSame(['14:15:00', '15:00:00'], array_values((array) DB::table('class_schedules')
            ->join('section_modules', 'section_modules.id', '=', 'class_schedules.section_module_id')
            ->join('modules', 'modules.id', '=', 'section_modules.module_id')
            ->where('modules.code', 'language')->first(['start_time', 'end_time'])));
    }

    public function test_active_session_shows_current_module_and_activity_scoped_to_teacher(): void
    {
        $this->seed(CurriculumSeeder::class);
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $quarterId = DB::table('quarters')->insertGetId([
            'school_year_id' => $year->id, 'quarter_number' => 1, 'name' => '1st Quarter',
            'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'status' => 'active',
        ]);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $sectionId = $this->actingAs($tina)
            ->postJson('/api/sections', ['gradeLevel' => 'Kinder 1', 'sectionName' => 'Joy'])
            ->json('sectionId');

        // No class in session yet: both names read null, not an error.
        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/active-session")->assertOk()
            ->assertJson(['activeModuleName' => null, 'activeActivityName' => null]);

        $sectionModuleId = DB::table('section_modules')
            ->join('modules', 'modules.id', '=', 'section_modules.module_id')
            ->where(['section_modules.section_id' => $sectionId, 'modules.code' => 'reading'])
            ->value('section_modules.id');
        $moduleDomainId = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where('modules.code', 'reading')->value('module_domains.id');
        $lessonId = DB::table('lessons')->insertGetId([
            'module_domain_id' => $moduleDomainId, 'title' => 'Letter A', 'sequence_order' => 1,
        ]);
        $activityId = DB::table('activities')->insertGetId([
            'lesson_id' => $lessonId, 'activity_code' => 'READ-A-1', 'name' => 'Trace the letter A',
            'instructions' => 'Trace it.', 'activity_format' => 'digital', 'sequence_order' => 1,
        ]);
        $sessionId = DB::table('classroom_sessions')->insertGetId([
            'section_module_id' => $sectionModuleId, 'quarter_id' => $quarterId, 'teacher_id' => $tina->id,
            'session_date' => now()->toDateString(), 'planned_start_time' => '08:00', 'planned_end_time' => '09:00',
            'started_at' => now(), 'status' => 'ongoing',
        ]);
        $sessionLessonId = DB::table('classroom_session_lessons')->insertGetId([
            'classroom_session_id' => $sessionId, 'lesson_id' => $lessonId, 'sequence_order' => 1, 'status' => 'active',
        ]);
        DB::table('classroom_session_activities')->insert([
            'session_lesson_id' => $sessionLessonId, 'activity_id' => $activityId, 'sequence_order' => 1,
            'assigned_by' => $tina->id, 'assigned_at' => now(), 'status' => 'active',
        ]);

        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/active-session")->assertOk()
            ->assertJson(['activeModuleName' => 'Reading', 'activeActivityName' => 'Trace the letter A']);

        // Another teacher's section is a 404, not someone else's live class.
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->actingAs($omar)->getJson("/api/sections/{$sectionId}/active-session")->assertNotFound();
    }

    public function test_curriculum_resources_counts_only_this_sections_modules(): void
    {
        $this->seed(CurriculumSeeder::class);
        SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $sectionId = $this->actingAs($tina)
            ->postJson('/api/sections', ['gradeLevel' => 'Kinder 1', 'sectionName' => 'Joy'])
            ->json('sectionId');

        // No lessons yet — the section has modules, but nothing under them.
        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/curriculum-resources")->assertOk()
            ->assertJson(['lessonCount' => 0, 'activityCount' => 0]);

        $readingDomainId = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where('modules.code', 'reading')->value('module_domains.id');
        $lesson1 = DB::table('lessons')->insertGetId(['module_domain_id' => $readingDomainId, 'title' => 'Letter A', 'sequence_order' => 1]);
        $lesson2 = DB::table('lessons')->insertGetId(['module_domain_id' => $readingDomainId, 'title' => 'Letter B', 'sequence_order' => 2]);
        DB::table('activities')->insert([
            ['lesson_id' => $lesson1, 'activity_code' => 'A-1', 'name' => 'Trace A', 'instructions' => '-', 'activity_format' => 'digital', 'sequence_order' => 1],
            ['lesson_id' => $lesson1, 'activity_code' => 'A-2', 'name' => 'Color A', 'instructions' => '-', 'activity_format' => 'practical', 'sequence_order' => 2],
            ['lesson_id' => $lesson2, 'activity_code' => 'B-1', 'name' => 'Trace B', 'instructions' => '-', 'activity_format' => 'digital', 'sequence_order' => 1],
        ]);

        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/curriculum-resources")->assertOk()
            ->assertJson(['lessonCount' => 2, 'activityCount' => 3]);

        // Another teacher's section is a 404, never someone else's counts.
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->actingAs($omar)->getJson("/api/sections/{$sectionId}/curriculum-resources")->assertNotFound();
    }

    public function test_overall_progress_percent_and_no_active_quarter_state(): void
    {
        $this->seed(CurriculumSeeder::class);
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $sectionId = $this->actingAs($tina)
            ->postJson('/api/sections', ['gradeLevel' => 'Kinder 1', 'sectionName' => 'Joy'])
            ->json('sectionId');

        // No quarter row at all yet.
        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/overall-progress")->assertOk()->assertContent('null');

        $quarterId = DB::table('quarters')->insertGetId([
            'school_year_id' => $year->id, 'quarter_number' => 1, 'name' => '1st Quarter',
            'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'status' => 'active',
        ]);

        $readingDomainId = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where('modules.code', 'reading')->value('module_domains.id');
        $lesson1 = DB::table('lessons')->insertGetId(['module_domain_id' => $readingDomainId, 'title' => 'Letter A', 'sequence_order' => 1, 'quarter_number' => 1]);
        $lesson2 = DB::table('lessons')->insertGetId(['module_domain_id' => $readingDomainId, 'title' => 'Letter B', 'sequence_order' => 2, 'quarter_number' => 1]);
        // A lesson in a different quarter must not count toward this quarter's total.
        DB::table('lessons')->insert(['module_domain_id' => $readingDomainId, 'title' => 'Letter C', 'sequence_order' => 3, 'quarter_number' => 2]);

        // 0 of 2 done yet.
        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/overall-progress")->assertOk()
            ->assertExactJson(['quarterId' => $quarterId, 'quarterName' => '1st Quarter', 'startDate' => '2026-06-01', 'percent' => 0]);

        $sectionModuleId = DB::table('section_modules')
            ->join('modules', 'modules.id', '=', 'section_modules.module_id')
            ->where(['section_modules.section_id' => $sectionId, 'modules.code' => 'reading'])
            ->value('section_modules.id');
        $sessionId = DB::table('classroom_sessions')->insertGetId([
            'section_module_id' => $sectionModuleId, 'quarter_id' => $quarterId, 'teacher_id' => $tina->id,
            'session_date' => now()->toDateString(), 'planned_start_time' => '08:00', 'planned_end_time' => '09:00', 'status' => 'completed',
        ]);
        DB::table('classroom_session_lessons')->insert([
            'classroom_session_id' => $sessionId, 'lesson_id' => $lesson1, 'sequence_order' => 1, 'status' => 'completed',
        ]);

        // 1 of 2 lessons for this quarter done => 50%.
        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/overall-progress")->assertOk()
            ->assertJson(['percent' => 50]);

        // Another teacher's section is a 404, never someone else's progress.
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->actingAs($omar)->getJson("/api/sections/{$sectionId}/overall-progress")->assertNotFound();
    }

    public function test_todays_schedule_shows_only_todays_module_times_and_current_quarter_lessons(): void
    {
        // A known Monday, so the default timetable's M/W/F modules are "today" and T/Th modules aren't.
        Carbon::setTestNow(Carbon::parse('2026-06-08 09:00:00', 'Asia/Manila'));

        $this->seed(CurriculumSeeder::class);
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $sectionId = $this->actingAs($tina)
            ->postJson('/api/sections', ['gradeLevel' => 'Kinder 1', 'sectionName' => 'Joy'])
            ->json('sectionId');

        // No active quarter yet: today's module times still show (the timetable doesn't need a quarter), but no lessons.
        $response = $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/todays-schedule")->assertOk()
            ->assertJsonCount(0, 'lessons')
            ->assertJsonFragment(['moduleId' => DB::table('modules')->where('code', 'reading')->value('id'), 'startTime' => '8:00 AM', 'endTime' => '9:00 AM']);
        // Monday has 3 module times (reading, language, writing) — MAPEH/Math's Tue/Thu slots never appear.
        $this->assertCount(3, $response->json('moduleTimes'));

        $quarterId = DB::table('quarters')->insertGetId([
            'school_year_id' => $year->id, 'quarter_number' => 1, 'name' => '1st Quarter',
            'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'status' => 'active',
        ]);
        $readingDomainId = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where('modules.code', 'reading')->value('module_domains.id');
        // Today's lesson: Monday (day_of_week 1), this quarter.
        $todayLessonId = DB::table('lessons')->insertGetId([
            'module_domain_id' => $readingDomainId, 'title' => 'Letter A', 'sequence_order' => 1,
            'day_of_week' => 1, 'quarter_number' => 1, 'week_number' => 2,
        ]);
        DB::table('activities')->insert([
            'lesson_id' => $todayLessonId, 'activity_code' => 'A-1', 'name' => 'Trace A',
            'instructions' => '-', 'activity_format' => 'digital', 'sequence_order' => 1,
        ]);
        // A Tuesday lesson and a next-quarter lesson must both stay out of today's list.
        DB::table('lessons')->insert([
            ['module_domain_id' => $readingDomainId, 'title' => 'Tuesday lesson', 'sequence_order' => 2, 'day_of_week' => 2, 'quarter_number' => 1, 'week_number' => 2],
            ['module_domain_id' => $readingDomainId, 'title' => 'Next quarter lesson', 'sequence_order' => 3, 'day_of_week' => 1, 'quarter_number' => 2, 'week_number' => 1],
        ]);
        unset($quarterId); // only its existence/activeness matters here

        $this->actingAs($tina)->getJson("/api/sections/{$sectionId}/todays-schedule")->assertOk()
            ->assertJsonCount(1, 'lessons')
            ->assertJsonPath('lessons.0.lessonName', 'Letter A')
            ->assertJsonPath('lessons.0.moduleName', 'Reading')
            ->assertJsonPath('lessons.0.week', 2)
            ->assertJsonPath('lessons.0.activities.0.activityName', 'Trace A');

        // Another teacher's section is a 404, never someone else's schedule.
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->actingAs($omar)->getJson("/api/sections/{$sectionId}/todays-schedule")->assertNotFound();
    }

    public function test_teacher_edits_a_sections_schedule_keeping_history(): void
    {
        $this->seed(CurriculumSeeder::class);
        SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $sectionId = $this->actingAs($tina)->postJson('/api/sections', ['gradeLevel' => 'Kinder 1', 'sectionName' => 'Joy'])->json('sectionId');

        $rows = collect($this->actingAs($tina)->getJson("/api/sections/$sectionId/schedule")->assertOk()->json())->keyBy('moduleName');
        $this->assertCount(5, $rows);
        $reading = $rows->first(fn ($r) => $r['startTime'] === '08:00' && $r['days'] === [1, 3, 5]);
        $this->assertNotNull($reading);

        $this->actingAs($omar)->getJson("/api/sections/$sectionId/schedule")->assertNotFound();

        $payload = fn (array $changes) => ['modules' => $rows->values()->map(fn ($r) => ($r['sectionModuleId'] === $reading['sectionModuleId'] ? $changes : []) + [
            'sectionModuleId' => $r['sectionModuleId'], 'startTime' => $r['startTime'], 'endTime' => $r['endTime'], 'days' => $r['days'],
        ])->all()];

        // End before start, and a clash with Language (9:15-10:00 MWF), are rejected.
        $this->actingAs($tina)->putJson("/api/sections/$sectionId/schedule", $payload(['startTime' => '09:00', 'endTime' => '08:30']))
            ->assertStatus(422);
        $this->actingAs($tina)->putJson("/api/sections/$sectionId/schedule", $payload(['startTime' => '09:00', 'endTime' => '09:30']))
            ->assertStatus(422);
        $this->assertSame(5, DB::table('class_schedules')->count());

        $saved = $this->actingAs($tina)->putJson("/api/sections/$sectionId/schedule", $payload(['startTime' => '07:00', 'endTime' => '08:00', 'days' => [1, 2]]))
            ->assertOk()->json();
        $updated = collect($saved)->firstWhere('sectionModuleId', $reading['sectionModuleId']);
        $this->assertSame(['07:00', '08:00', [1, 2]], [$updated['startTime'], $updated['endTime'], $updated['days']]);

        // Only the changed module got a new row; its old one stays, inactive.
        $this->assertSame(6, DB::table('class_schedules')->count());
        $this->assertFalse((bool) DB::table('class_schedules')->where('id', $reading['scheduleId'])->value('is_active'));
        $this->assertSame(5, DB::table('class_schedules')->where('is_active', true)->count());
    }

    public function test_schedule_save_leaves_a_never_scheduled_module_blank(): void
    {
        $this->seed(CurriculumSeeder::class);
        SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $sectionId = $this->actingAs($tina)->postJson('/api/sections', ['gradeLevel' => 'Kinder 1', 'sectionName' => 'Joy'])->json('sectionId');
        $domainId = DB::table('developmental_domains')->value('id');
        $this->actingAs($tina)->postJson('/api/modules', ['moduleName' => 'Science', 'domainIds' => [$domainId]])->assertCreated();

        $rows = collect($this->actingAs($tina)->getJson("/api/sections/$sectionId/schedule")->assertOk()->json());
        $science = $rows->firstWhere('moduleName', 'Science');
        $this->assertSame([null, null, []], [$science['startTime'], $science['endTime'], $science['days']]);

        $payload = fn (callable $change) => ['modules' => $rows->map(fn ($r) => $change([
            'sectionModuleId' => $r['sectionModuleId'], 'startTime' => $r['startTime'], 'endTime' => $r['endTime'], 'days' => $r['days'],
        ], $r))->all()];

        // The blank Science row is skipped, so another module's change still saves.
        $this->actingAs($tina)->putJson("/api/sections/$sectionId/schedule", $payload(
            fn ($m, $r) => $r['moduleName'] === 'Reading' ? ['startTime' => '07:00', 'endTime' => '08:00'] + $m : $m,
        ))->assertOk();
        $this->assertSame(6, DB::table('class_schedules')->count());

        // A half-filled new row, or blanking a module that has a schedule, is rejected.
        $this->actingAs($tina)->putJson("/api/sections/$sectionId/schedule", $payload(
            fn ($m, $r) => $r['moduleName'] === 'Science' ? ['startTime' => '13:00'] + $m : $m,
        ))->assertStatus(422)->assertJsonValidationErrors([
            'modules.'.$rows->search($science).'.endTime',
            'modules.'.$rows->search($science).'.days',
        ]);
        $this->actingAs($tina)->putJson("/api/sections/$sectionId/schedule", $payload(
            fn ($m, $r) => $r['moduleName'] === 'Math' ? ['startTime' => null, 'endTime' => null, 'days' => []] + $m : $m,
        ))->assertStatus(422);
        $this->assertSame(6, DB::table('class_schedules')->count());
    }
}

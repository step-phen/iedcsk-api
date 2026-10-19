<?php

namespace Tests\Feature;

use App\Models\SchoolYear;
use App\Models\Teacher;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CurriculumTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_section_checklist_lists_enrolled_learners_items_and_ratings(): void
    {
        $this->seed(CurriculumSeeder::class);
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $section = $this->setUpSections($tina, ['Joy'])['Joy'];

        $learner = fn (string $first, string $last, string $status) => DB::table('learner_school_years')->insertGetId([
            'learner_id' => DB::table('learners')->insertGetId(['first_name' => $first, 'last_name' => $last, 'birth_date' => '2021-01-01']),
            'school_year_id' => $year->id, 'section_id' => $section, 'enrolled_on' => '2026-06-01', 'status' => $status,
        ]);
        $plaza = $learner('Stephen', 'Plaza', 'active');
        $abad = $learner('Ana', 'Abad', 'active');
        $learner('Gone', 'Away', 'transferred');

        $math = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
        $counting = DB::table('activities')->insertGetId(['lesson_id' => DB::table('lessons')->insertGetId([
            'module_domain_id' => DB::table('module_domains')->where('domain_id', $math)->value('id'), 'title' => 'Counting', 'quarter_number' => 2, 'week_number' => 1, 'sequence_order' => 1,
        ]), 'activity_code' => 'MT-Q2W1-01', 'name' => 'Count the Apples', 'instructions' => '-', 'activity_format' => 'digital', 'sequence_order' => 1]);
        $item = DB::table('developmental_checklist_items')->insertGetId(['domain_id' => $math, 'item' => 'Counts to 10', 'grade_level' => 'Kindergarten', 'quarter_number' => 2, 'activity_id' => $counting, 'display_order' => 1]);
        DB::table('developmental_checklist_items')->insert(['domain_id' => $math, 'item' => 'Other grade', 'grade_level' => 'Grade 1', 'quarter_number' => 1, 'display_order' => 2]);
        $q2 = DB::table('quarters')->insertGetId(['school_year_id' => $year->id, 'quarter_number' => 2, 'name' => 'Q2', 'start_date' => '2026-08-01', 'end_date' => '2026-10-31', 'status' => 'active']);
        DB::table('learner_developmental_checklists')->insert([
            'enrollment_id' => $plaza, 'checklist_item_id' => $item, 'quarter_id' => $q2, 'teacher_id' => $tina->id, 'rating' => 'developing', 'assessed_at' => now(),
        ]);

        // Two finished tries at the linked game; only the latest score comes back.
        $assignment = DB::table('learner_activity_assignments')->insertGetId([
            'enrollment_id' => $plaza, 'activity_id' => $counting, 'quarter_id' => $q2, 'teacher_id' => $tina->id, 'assigned_at' => now(), 'status' => 'completed', 'completed_at' => now(),
        ]);
        DB::table('learner_activity_attempts')->insert([
            ['assignment_id' => $assignment, 'attempt_number' => 1, 'status' => 'evaluated', 'score' => 2, 'max_score' => 5, 'started_at' => now(), 'evaluated_at' => now()->subDay()],
            ['assignment_id' => $assignment, 'attempt_number' => 2, 'status' => 'evaluated', 'score' => 4, 'max_score' => 5, 'started_at' => now(), 'evaluated_at' => now()],
        ]);

        $data = $this->actingAs($tina)->getJson("/api/sections/{$section}/checklist")->assertOk()->json();
        $this->assertEquals([['enrollmentId' => $plaza, 'activityId' => $counting, 'score' => 4, 'maxScore' => 5]], $data['results']);
        $this->assertSame(['Abad', 'Plaza'], array_column($data['learners'], 'lastName'));
        $this->assertSame([['itemId' => $item, 'item' => 'Counts to 10', 'quarter' => 2, 'activityId' => $counting, 'activityName' => 'Count the Apples', 'hasRecords' => true]], collect($data['domains'])->firstWhere('domainId', $math)['items']);
        $this->assertSame([['enrollmentId' => $plaza, 'itemId' => $item, 'quarter' => 2, 'rating' => 'developing', 'remarks' => null]], $data['ratings']);
        $this->assertSame('2nd Quarter', $data['quarters'][1]['quarterName']);

        $this->actingAs($omar)->getJson("/api/sections/{$section}/checklist")->assertNotFound();

        // Tapping a cell rates it, picking "Not rated" clears it, and only the quarter's own items can be rated.
        $rate = fn (array $body, $teacher = null) => $this->actingAs($teacher ?? $tina)->putJson("/api/sections/{$section}/checklist/ratings", $body + ['enrollmentId' => $plaza, 'itemId' => $item, 'quarter' => 2]);
        $rated = fn () => DB::table('learner_developmental_checklists')->where(['enrollment_id' => $plaza, 'checklist_item_id' => $item])->value('rating');
        // Abad has not finished the linked game, so the skill cannot be rated for her yet; clearing still works.
        $rate(['rating' => 'achieved', 'enrollmentId' => $abad])->assertJsonValidationErrors(['rating' => 'This learner has not finished the activity yet, so this skill cannot be rated.']);
        $rate(['rating' => null, 'enrollmentId' => $abad])->assertOk();
        $rate(['rating' => 'achieved'])->assertOk()->assertJson(['rating' => 'achieved']);
        $this->assertSame('achieved', $rated());
        // Remarks are kept when a later save leaves them out, and go away with the rating.
        $remarks = fn () => DB::table('learner_developmental_checklists')->where(['enrollment_id' => $plaza, 'checklist_item_id' => $item])->value('remarks');
        $rate(['rating' => 'achieved', 'remarks' => 'Matched all five on her own.'])->assertOk()->assertJson(['remarks' => 'Matched all five on her own.']);
        $rate(['rating' => 'developing'])->assertOk()->assertJson(['remarks' => 'Matched all five on her own.']);
        $rate(['rating' => 'developing', 'remarks' => str_repeat('a', 501)])->assertJsonValidationErrors('remarks');
        $rate(['rating' => 'developing', 'remarks' => ''])->assertOk();
        $this->assertNull($remarks());
        $rate(['rating' => 'achieved', 'remarks' => 'Again.'])->assertOk();
        $rate(['rating' => null])->assertOk();
        $this->assertNull($rated());
        $this->assertNull($remarks());
        $rate(['rating' => 'great'])->assertJsonValidationErrors('rating');
        $rate(['rating' => 'achieved', 'quarter' => 1])->assertJsonValidationErrors('itemId');
        $rate(['rating' => 'achieved'], $omar)->assertNotFound();
    }

    public function test_teacher_adds_a_checklist_item(): void
    {
        $this->postJson('/api/checklist-items', [])->assertUnauthorized();

        $this->seed(CurriculumSeeder::class);
        SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $section = $this->setUpSections($tina, ['Joy'])['Joy'];
        $math = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');
        $writing = DB::table('developmental_domains')->where('code', 'writing_readiness')->value('id');
        $counting = DB::table('activities')->insertGetId(['lesson_id' => DB::table('lessons')->insertGetId([
            'module_domain_id' => DB::table('module_domains')->where('domain_id', $math)->value('id'), 'title' => 'Counting', 'quarter_number' => 1, 'week_number' => 1, 'sequence_order' => 1,
        ]), 'activity_code' => 'MT-Q1W1-01', 'name' => 'Count the Apples', 'instructions' => '-', 'activity_format' => 'digital', 'sequence_order' => 1]);

        $add = fn (array $body) => $this->actingAs($tina)->postJson('/api/checklist-items', $body + ['domainId' => $math, 'quarter' => 1]);

        // Rejected requests write nothing.
        $add(['item' => ' '])->assertJsonValidationErrors('item');
        $add(['item' => str_repeat('a', 101)])->assertJsonValidationErrors('item');
        $add(['item' => 'Counts to 5', 'quarter' => 5])->assertJsonValidationErrors('quarter');
        $add(['item' => 'Counts to 5', 'activityId' => $counting, 'quarter' => 2])->assertJsonValidationErrors('activityId');
        $add(['item' => 'Counts to 5', 'activityId' => $counting, 'domainId' => $writing])->assertJsonValidationErrors('activityId');
        $this->assertSame(0, DB::table('developmental_checklist_items')->count());

        $add(['item' => ' Counts to 5 ', 'activityId' => $counting])->assertCreated()
            ->assertJson(['item' => 'Counts to 5', 'quarter' => 1, 'activityName' => 'Count the Apples']);
        $add(['item' => 'Writes 1 to 5'])->assertCreated()->assertJson(['activityName' => null]);
        $add(['item' => 'counts to 5'])->assertJsonValidationErrors('item');
        $this->assertSame([1, 2], DB::table('developmental_checklist_items')->orderBy('id')->pluck('display_order')->all());

        // It shows on the section's checklist, and the domain lists the games it can be linked to.
        $domain = collect($this->actingAs($tina)->getJson("/api/sections/{$section}/checklist")->assertOk()->json('domains'))->firstWhere('domainId', $math);
        $this->assertSame(['Counts to 5', 'Writes 1 to 5'], array_column($domain['items'], 'item'));
        $this->assertSame([['activityId' => $counting, 'name' => 'Count the Apples', 'quarter' => 1, 'hasRecords' => false]], $domain['activities']);

        // Editing renames and relinks in place; domain and quarter in the body are ignored.
        $first = $domain['items'][0]['itemId'];
        $edit = fn (array $body, int $id = 0) => $this->actingAs($tina)->putJson('/api/checklist-items/'.($id ?: $first), $body);
        $edit(['item' => 'Writes 1 to 5'])->assertJsonValidationErrors('item');
        $edit(['item' => ' '])->assertJsonValidationErrors('item');
        $edit(['item' => 'Counts 1 to 5', 'domainId' => $writing, 'quarter' => 3])->assertOk()
            ->assertJson(['itemId' => $first, 'item' => 'Counts 1 to 5', 'quarter' => 1, 'activityId' => null, 'activityName' => null]);
        $edit(['item' => 'counts 1 to 5', 'activityId' => $counting])->assertOk()->assertJson(['item' => 'counts 1 to 5', 'activityName' => 'Count the Apples']);
        $this->assertSame([$math, 1], [DB::table('developmental_checklist_items')->where('id', $first)->value('domain_id'), DB::table('developmental_checklist_items')->where('id', $first)->value('quarter_number')]);
        $edit(['item' => 'Anything'], 99999)->assertNotFound();
    }

    public function test_teacher_adds_a_module(): void
    {
        $this->postJson('/api/modules', ['moduleName' => 'Science'])->assertUnauthorized();
        $this->getJson('/api/domains')->assertUnauthorized();

        $this->seed(CurriculumSeeder::class);
        // The active year has no quarters set up yet: adding a module isn't quarter-specific, so it still works.
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $old = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-06-01', 'end_date' => '2026-03-31', 'status' => 'completed']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        ['Joy' => $joy, 'Faith' => $faith] = $this->setUpSections($tina);
        // Faith is Omar's class.
        DB::table('sections')->where('id', $faith)->update(['teacher_id' => $omar->id]);
        DB::table('section_modules')->where('section_id', $faith)->update(['teacher_id' => $omar->id]);
        $oldSection = DB::table('sections')->insertGetId(['school_year_id' => $old->id, 'teacher_id' => $tina->id, 'name' => 'Hope', 'grade_level' => 'Kindergarten']);
        $math = DB::table('developmental_domains')->where('code', 'math_readiness')->value('id');

        $counts = fn () => [DB::table('modules')->count(), DB::table('developmental_domains')->count(), DB::table('module_domains')->count(), DB::table('section_modules')->count()];
        $before = $counts();

        // Rejected requests write nothing.
        $this->actingAs($tina)->postJson('/api/modules', ['moduleName' => 'Science'])->assertStatus(422)->assertJsonValidationErrors('domainIds');
        $this->actingAs($tina)->postJson('/api/modules', ['moduleName' => 'rEaDiNg', 'domainIds' => [$math]])->assertStatus(422)->assertJsonValidationErrors('moduleName');
        $this->actingAs($tina)->postJson('/api/modules', ['moduleName' => '!!!', 'domainIds' => [$math]])->assertStatus(422)->assertJsonValidationErrors('moduleName');
        $this->actingAs($tina)->postJson('/api/modules', ['moduleName' => '   ', 'domainIds' => [], 'newDomain' => '  '])->assertStatus(422)->assertJsonValidationErrors('moduleName');
        $this->actingAs($tina)->postJson('/api/modules', ['moduleName' => str_repeat('a', 51), 'domainIds' => [$math]])->assertStatus(422)->assertJsonValidationErrors('moduleName');
        $this->actingAs($tina)->postJson('/api/modules', ['moduleName' => 'Science', 'newDomain' => str_repeat('a', 151)])->assertStatus(422)->assertJsonValidationErrors('newDomain');
        $this->actingAs($tina)->postJson('/api/modules', ['moduleName' => 'Science', 'newDomain' => 'math readiness'])->assertStatus(422)->assertJsonValidationErrors('newDomain');
        $this->actingAs($tina)->postJson('/api/modules', ['moduleName' => 'Science', 'domainIds' => [99999]])->assertStatus(422)->assertJsonValidationErrors('domainIds.0');
        $this->assertSame($before, $counts());

        $created = $this->actingAs($tina)->postJson('/api/modules', ['moduleName' => ' Science Explorers ', 'domainIds' => [$math], 'newDomain' => ' Science Discovery '])
            ->assertCreated()->assertJson(['moduleName' => 'Science Explorers', 'scheduleDays' => [], 'lessons' => []])->json();

        $module = DB::table('modules')->find($created['id']);
        $this->assertSame(['science_explorers', 'Science Explorers', 'Kindergarten'], [$module->code, $module->name, $module->grade_level]);

        $newDomain = DB::table('developmental_domains')->where('name', 'Science Discovery')->first();
        $this->assertSame('science_discovery', $newDomain->code);
        $this->assertSame(DB::table('developmental_domains')->max('display_order'), $newDomain->display_order);
        $this->assertSame(['Math Readiness', 'Science Discovery'], array_column($created['domains'], 'name'));

        // Linked to every section of the active year, each with its own teacher; not to old years.
        $links = DB::table('section_modules')->where('module_id', $created['id'])->orderBy('section_id')->get(['section_id', 'teacher_id']);
        $this->assertSame([[$joy, $tina->id], [$faith, $omar->id]], $links->map(fn ($l) => [$l->section_id, $l->teacher_id])->all());
        $this->assertFalse(DB::table('section_modules')->where('section_id', $oldSection)->exists());

        $this->assertSame(0, DB::table('quarters')->count());

        // It shows on the board and takes lessons like any other module, once their quarter is set up as upcoming.
        $board = collect($this->actingAs($tina)->getJson("/api/modules?section={$joy}")->assertOk()->json())->firstWhere('id', $created['id']);
        $this->assertSame('Science Explorers', $board['moduleName']);
        $plants = ['moduleDomainId' => $created['domains'][1]['id'], 'title' => 'Plants', 'quarter' => 1, 'week' => 1, 'day' => 2];
        $this->actingAs($tina)->postJson("/api/modules/{$created['id']}/lessons", $plants)->assertStatus(422)->assertJsonValidationErrors(['quarter' => "The 1st Quarter isn't set up"]);
        $this->upcomingQuarterOne($year);
        $this->actingAs($tina)->postJson("/api/modules/{$created['id']}/lessons", $plants)->assertCreated();

        $this->assertSame('Science Discovery', collect($this->actingAs($tina)->getJson('/api/domains')->assertOk()->json())->last()['domainName']);
        $this->actingAs($tina)->postJson('/api/modules', ['moduleName' => 'science explorers', 'domainIds' => [$math]])->assertStatus(422)->assertJsonValidationErrors('moduleName');
    }

    public function test_modules_board_and_week_lessons(): void
    {
        $this->getJson('/api/modules')->assertUnauthorized();
        // The board only shows a timetable valid today, so pin today inside the school year.
        Carbon::setTestNow(Carbon::parse('2026-09-28 09:00', 'Asia/Manila'));

        $this->seed(CurriculumSeeder::class);
        SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $joy = $this->setUpSections($tina, ['Joy'])['Joy'];

        $reading = DB::table('modules')->where('code', 'reading')->value('id');
        $mapping = DB::table('module_domains')->where('module_id', $reading)->value('id');
        $lesson = DB::table('lessons')->insertGetId(['module_domain_id' => $mapping, 'title' => 'Vowel Aa', 'quarter_number' => 1, 'week_number' => 2, 'sequence_order' => 1]);
        DB::table('activities')->insert([
            ['lesson_id' => $lesson, 'activity_code' => 'vowel-aa', 'name' => 'Vowel Aa', 'instructions' => 'Tap', 'activity_format' => 'digital', 'sequence_order' => 1],
            ['lesson_id' => $lesson, 'activity_code' => 'trace-aa', 'name' => 'Trace Aa', 'instructions' => 'Trace', 'activity_format' => 'practical', 'sequence_order' => 2],
        ]);

        $modules = collect($this->actingAs($tina)->getJson("/api/modules?section={$joy}")->assertOk()->json());
        $this->assertSame(['Reading', 'Language', 'Writing', 'MAPEH', 'Math'], $modules->pluck('moduleName')->all());

        $board = $modules->firstWhere('moduleName', 'Reading');
        $this->assertSame(['MONDAY', 'WEDNESDAY', 'FRIDAY'], array_column($board['scheduleDays'], 'day'));
        $this->assertSame('8:00 AM', $board['scheduleDays'][0]['startTime']);
        $this->assertSame(['quarterId' => 1, 'quarterName' => '1st Quarter', 'week' => 2, 'activityCount' => 2],
            array_intersect_key($board['lessons'][0], array_flip(['quarterId', 'quarterName', 'week', 'activityCount'])));

        $this->actingAs($tina)->getJson("/api/modules/{$reading}/lessons?quarter=1&week=2")->assertOk()
            ->assertJsonPath('0.lessonName', 'Vowel Aa')
            ->assertJsonPath('0.activities.1.format', 'practical');
        $this->actingAs($tina)->getJson("/api/modules/{$reading}/lessons?quarter=9&week=2")->assertStatus(422);
    }

    public function test_modules_board_schedule_belongs_to_the_selected_section_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 09:00', 'Asia/Manila'));
        $this->seed(CurriculumSeeder::class);
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-16', 'end_date' => '2027-04-30', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        ['Joy' => $joy, 'Faith' => $faith] = $this->setUpSections($tina);
        // Faith moves to the afternoon; Joy keeps the default morning timetable.
        foreach (['reading' => ['13:00', '14:00'], 'language' => ['14:15', '15:00'], 'writing' => ['15:00', '16:00'], 'mapeh' => ['13:00', '14:30'], 'math' => ['14:45', '16:00']] as $code => [$start, $end]) {
            DB::table('class_schedules')->whereIn('section_module_id', DB::table('section_modules')
                ->join('modules', 'modules.id', '=', 'section_modules.module_id')
                ->where('section_modules.section_id', $faith)->where('modules.code', $code)->select('section_modules.id'))
                ->update(['start_time' => $start, 'end_time' => $end]);
        }
        $reading = DB::table('modules')->where('code', 'reading')->value('id');
        DB::table('lessons')->insert(['module_domain_id' => DB::table('module_domains')->where('module_id', $reading)->value('id'), 'title' => 'Vowel Aa', 'quarter_number' => 1, 'week_number' => 1, 'sequence_order' => 1]);

        // "Tuesday 1:00 PM–2:30 PM" style summary per module, plus the lesson titles.
        $board = fn (string $query) => collect($this->actingAs($tina)->getJson("/api/modules{$query}")->assertOk()->json())
            ->mapWithKeys(fn ($m) => [$m['moduleName'] => [
                'days' => array_column($m['scheduleDays'], 'day'),
                'times' => array_values(array_unique(array_map(fn ($d) => "{$d['startTime']}-{$d['endTime']}", $m['scheduleDays']))),
                'lessons' => array_column($m['lessons'], 'lessonName'),
                'domains' => count($m['domains']),
            ]]);

        $joyBoard = $board("?section={$joy}");
        $this->assertSame(['days' => ['MONDAY', 'WEDNESDAY', 'FRIDAY'], 'times' => ['8:00 AM-9:00 AM'], 'lessons' => ['Vowel Aa'], 'domains' => 1], $joyBoard['Reading']);
        $this->assertSame(['days' => ['TUESDAY', 'THURSDAY'], 'times' => ['8:00 AM-9:30 AM'], 'lessons' => [], 'domains' => 3], $joyBoard['MAPEH']);

        $faithBoard = $board("?section={$faith}");
        $this->assertSame(['days' => ['MONDAY', 'WEDNESDAY', 'FRIDAY'], 'times' => ['1:00 PM-2:00 PM'], 'lessons' => ['Vowel Aa'], 'domains' => 1], $faithBoard['Reading']);
        $this->assertSame(['days' => ['TUESDAY', 'THURSDAY'], 'times' => ['1:00 PM-2:30 PM'], 'lessons' => [], 'domains' => 3], $faithBoard['MAPEH']);
        $this->assertSame(['9:15 AM-10:00 AM'], $joyBoard['Language']['times']);
        $this->assertSame(['2:45 PM-4:00 PM'], $faithBoard['Math']['times']);

        // Without a section: the same shared curriculum, and no schedule rather than a merged one.
        $plain = $board('');
        $this->assertSame([], $plain['Reading']['days']);
        $this->assertSame(['Vowel Aa'], $plain['Reading']['lessons']);
        $this->assertSame(['Reading', 'Language', 'Writing', 'MAPEH', 'Math'], $plain->keys()->all());

        // Another teacher's section, an old year's section and junk ids are all 404s.
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->actingAs($omar)->getJson("/api/modules?section={$joy}")->assertNotFound();
        foreach (['999', 'abc', '1.5', '-1'] as $bad) {
            $this->actingAs($tina)->getJson("/api/modules?section={$bad}")->assertNotFound();
        }
        $year->update(['status' => 'completed']);
        $this->actingAs($tina)->getJson("/api/modules?section={$joy}")->assertNotFound();
    }

    // An active 2026-2027 whose 1st Quarter is upcoming, the only state in which its lessons can be prepared.
    private function upcomingQuarterOne(?SchoolYear $year = null): void
    {
        $year ??= SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        DB::table('quarters')->insert(['school_year_id' => $year->id, 'quarter_number' => 1, 'name' => 'Q1', 'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'status' => 'upcoming']);
    }

    public function test_teacher_adds_lesson_to_module_domain(): void
    {
        $this->seed(CurriculumSeeder::class);
        $this->upcomingQuarterOne();
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $reading = DB::table('modules')->where('code', 'reading')->value('id');
        $mapeh = DB::table('modules')->where('code', 'mapeh')->value('id');
        $readingDomain = DB::table('module_domains')->where('module_id', $reading)->value('id');
        $mapehDomain = DB::table('module_domains')->where('module_id', $mapeh)->value('id');
        $payload = ['moduleDomainId' => $readingDomain, 'title' => ' Vowel Ee ', 'quarter' => 1, 'week' => 3, 'day' => 1];

        $this->postJson("/api/modules/{$reading}/lessons", $payload)->assertUnauthorized();
        $this->actingAs($tina)->postJson("/api/modules/{$reading}/lessons", $payload)->assertCreated()
            ->assertJson(['lessonName' => 'Vowel Ee', 'moduleId' => $reading, 'quarterId' => 1, 'week' => 3, 'dayOfWeek' => 1, 'activityCount' => 0]);
        $this->actingAs($tina)->postJson("/api/modules/{$reading}/lessons", ['title' => 'Vowel Ii'] + $payload)->assertCreated();
        $this->assertSame([1, 2], DB::table('lessons')->orderBy('id')->pluck('sequence_order')->all());

        // A domain belonging to another module is rejected.
        $this->actingAs($tina)->postJson("/api/modules/{$reading}/lessons", ['moduleDomainId' => $mapehDomain] + $payload)
            ->assertJsonValidationErrors('moduleDomainId');
        $this->actingAs($tina)->postJson("/api/modules/{$reading}/lessons", ['title' => '', 'week' => 0, 'day' => 8] + $payload)
            ->assertJsonValidationErrors(['title', 'week', 'day']);

        $disk = Storage::fake('public');
        $video = UploadedFile::fake()->create('vowel-oo.mp4', 1024, 'video/mp4');
        $url = $this->actingAs($tina)->post("/api/modules/{$reading}/lessons", ['title' => 'Vowel Oo', 'video' => $video] + $payload, ['Accept' => 'application/json'])
            ->assertCreated()->json('videoUrl');
        $path = DB::table('lessons')->where('title', 'Vowel Oo')->value('lesson_video_url');
        $disk->assertExists($path);
        $this->assertStringEndsWith($path, $url);
        $this->actingAs($tina)->post("/api/modules/{$reading}/lessons", ['video' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')] + $payload, ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('video');
        $this->actingAs($tina)->post("/api/modules/{$reading}/lessons", ['video' => UploadedFile::fake()->create('clip.txt', 10, 'video/mp4')] + $payload, ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('video');

        // A lesson without a video can get one later, but only once.
        $noVideo = DB::table('lessons')->where('title', 'Vowel Ee')->value('id');
        $attach = fn () => $this->actingAs($tina)->post("/api/lessons/{$noVideo}/video", ['video' => UploadedFile::fake()->create('ee.mp4', 512, 'video/mp4')], ['Accept' => 'application/json']);
        $attach()->assertOk()->assertJsonPath('lessonId', $noVideo);
        $disk->assertExists(DB::table('lessons')->where('id', $noVideo)->value('lesson_video_url'));
        $attach()->assertJsonValidationErrors('video');
        $this->actingAs($tina)->post('/api/lessons/99999/video', ['video' => UploadedFile::fake()->create('x.mp4', 1, 'video/mp4')], ['Accept' => 'application/json'])->assertNotFound();
        $readingBoard = collect($this->actingAs($tina)->getJson('/api/modules')->json())->firstWhere('moduleName', 'Reading');
        $this->assertStringContainsString('/storage/lesson-videos/', collect($readingBoard['lessons'])->firstWhere('lessonName', 'Vowel Ee')['videoUrl']);

        // Removing the video deletes its file and frees the lesson for a new upload.
        $oldPath = DB::table('lessons')->where('id', $noVideo)->value('lesson_video_url');
        $this->actingAs($tina)->deleteJson("/api/lessons/{$noVideo}/video")->assertOk()->assertJson(['videoUrl' => null]);
        $disk->assertMissing($oldPath);
        $this->assertNull(DB::table('lessons')->where('id', $noVideo)->value('lesson_video_url'));
        $attach()->assertOk();
        $this->actingAs($tina)->deleteJson('/api/lessons/99999/video')->assertNotFound();

        // The description can be edited or cleared.
        $this->actingAs($tina)->patchJson("/api/lessons/{$noVideo}", ['description' => ' Say Ee. '])->assertOk()->assertJson(['description' => 'Say Ee.']);
        $this->actingAs($tina)->patchJson("/api/lessons/{$noVideo}", ['description' => ''])->assertOk()->assertJson(['description' => null]);
        $this->actingAs($tina)->patchJson("/api/lessons/{$noVideo}", ['description' => str_repeat('a', 2001)])->assertJsonValidationErrors('description');
        $this->actingAs($tina)->patchJson('/api/lessons/99999', ['description' => 'x'])->assertNotFound();

        $board = collect($this->actingAs($tina)->getJson('/api/modules')->json())->firstWhere('moduleName', 'MAPEH');
        $this->assertCount(3, $board['domains']);
    }

    public function test_teacher_creates_a_practical_activity(): void
    {
        $this->seed(CurriculumSeeder::class);
        $this->upcomingQuarterOne();
        $disk = Storage::fake('public');
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $lesson = DB::table('lessons')->insertGetId([
            'module_domain_id' => DB::table('module_domains')->value('id'), 'title' => 'Hopping', 'quarter_number' => 1, 'week_number' => 1, 'day_of_week' => 1, 'sequence_order' => 1,
        ]);
        $payload = ['name' => ' Hop on one foot ', 'description' => 'Balance practice.', 'instructions' => 'Hop five times on your left foot.', 'teacherGuidelines' => 'Very Good: five hops without falling.'];
        $post = fn (array $body, int $to = 0) => $this->actingAs($tina)->post('/api/lessons/'.($to ?: $lesson).'/practical-activities', $body, ['Accept' => 'application/json']);
        $video = fn () => UploadedFile::fake()->create('hop.mp4', 512, 'video/mp4');

        $id = $post($payload + ['video' => $video()])->assertCreated()
            ->assertJson(['activityName' => 'Hop on one foot', 'description' => 'Balance practice.', 'format' => 'practical'])->json('id');
        $activity = DB::table('activities')->where('id', $id)->first();
        $this->assertSame(['Hop on one foot', 'practical', 1, "PR-L{$lesson}-1"], [$activity->name, $activity->activity_format, $activity->sequence_order, $activity->activity_code]);
        $disk->assertExists(DB::table('practical_activity_details')->where('activity_id', $id)->value('demo_video_url'));

        // A second one goes after it, and it shows in the week's activities.
        $post(['name' => 'Jump'] + $payload + ['video' => $video()])->assertCreated();
        $week = $this->actingAs($tina)->getJson('/api/modules/'.DB::table('module_domains')->value('module_id').'/lessons?quarter=1&week=1')->json('0.activities');
        $this->assertSame(['Hop on one foot', 'Jump'], array_column($week, 'activityName'));

        // Every text field is required, the video is optional, and a sent video must really be a video.
        $post([])->assertJsonValidationErrors(['name', 'description', 'instructions', 'teacherGuidelines'])->assertJsonMissingValidationErrors('video');
        $post($payload + ['video' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->assertJsonValidationErrors('video');
        $post($payload + ['video' => $video()], 99999)->assertNotFound();

        // Editing changes the text and keeps the video unless a new one is sent; a new one replaces the old file.
        $edit = fn (array $body, int $which = 0) => $this->actingAs($tina)->post('/api/practical-activities/'.($which ?: $id), $body, ['Accept' => 'application/json']);
        $oldVideo = DB::table('practical_activity_details')->where('activity_id', $id)->value('demo_video_url');
        $edit(['name' => 'Hop twice', 'teacherGuidelines' => 'Two hops.'] + $payload)->assertOk()
            ->assertJson(['activityName' => 'Hop twice', 'teacherGuidelines' => 'Two hops.', 'instructions' => 'Hop five times on your left foot.']);
        $this->assertSame($oldVideo, DB::table('practical_activity_details')->where('activity_id', $id)->value('demo_video_url'));
        $edit($payload + ['video' => $video()])->assertOk();
        $disk->assertMissing($oldVideo);
        $edit(['name' => ''] + $payload)->assertJsonValidationErrors('name');
        $digital = DB::table('activities')->insertGetId(['lesson_id' => $lesson, 'activity_code' => 'DG-1', 'name' => 'Game', 'instructions' => '-', 'activity_format' => 'digital', 'sequence_order' => 9]);
        $edit($payload, $digital)->assertNotFound();

        // Deleting hides it and unlinks its skills, but not while a class is running it.
        $skill = DB::table('developmental_checklist_items')->insertGetId(['domain_id' => DB::table('developmental_domains')->value('id'), 'item' => 'Hops', 'grade_level' => 'Kindergarten', 'quarter_number' => 1, 'activity_id' => $id, 'display_order' => 1]);
        $this->actingAs($tina)->deleteJson("/api/practical-activities/{$digital}")->assertNotFound();
        $this->actingAs($tina)->deleteJson("/api/practical-activities/{$id}")->assertOk();
        $this->assertFalse((bool) DB::table('activities')->where('id', $id)->value('is_active'));
        $this->assertNull(DB::table('developmental_checklist_items')->where('id', $skill)->value('activity_id'));
        $this->assertSame(['Jump', 'Game'], array_column($this->actingAs($tina)->getJson('/api/modules/'.DB::table('module_domains')->value('module_id').'/lessons?quarter=1&week=1')->json('0.activities'), 'activityName'));
        $this->actingAs($tina)->deleteJson("/api/practical-activities/{$id}")->assertNotFound();
    }

    /**
     * An active 2026-2027 with Q2 open for classes, and one Reading lesson in Q1 and in Q2.
     *
     * @return array{Teacher, int, int, int, int} teacher, Reading module, its domain, Q1 lesson, Q2 lesson
     */
    private function quarterTwoActive(): array
    {
        $this->seed(CurriculumSeeder::class);
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        foreach ([1 => 'upcoming', 2 => 'active'] as $number => $status) {
            DB::table('quarters')->insert(['school_year_id' => $year->id, 'quarter_number' => $number, 'name' => "Q{$number}", 'start_date' => '2026-06-01', 'end_date' => '2026-12-31', 'status' => $status]);
        }
        $reading = DB::table('modules')->where('code', 'reading')->value('id');
        $domain = DB::table('module_domains')->where('module_id', $reading)->value('id');
        $lesson = fn (int $quarter) => DB::table('lessons')->insertGetId(['module_domain_id' => $domain, 'title' => "Lesson Q{$quarter}", 'quarter_number' => $quarter, 'week_number' => 1, 'day_of_week' => 1, 'sequence_order' => 1]);

        return [Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']), $reading, $domain, $lesson(1), $lesson(2)];
    }

    private function practical(int $lesson, string $name): int
    {
        $id = DB::table('activities')->insertGetId(['lesson_id' => $lesson, 'activity_code' => "PR-{$name}", 'name' => $name, 'instructions' => '-', 'activity_format' => 'practical', 'sequence_order' => 1]);
        DB::table('practical_activity_details')->insert(['activity_id' => $id, 'teacher_guidelines' => '-']);

        return $id;
    }

    public function test_active_quarter_lessons_and_practical_activities_are_read_only(): void
    {
        [$tina, $reading, $domain, $q1, $q2] = $this->quarterTwoActive();
        $disk = Storage::fake('public');
        $disk->put('lesson-videos/old.mp4', 'x');
        $q2Video = DB::table('lessons')->insertGetId(['module_domain_id' => $domain, 'title' => 'With video', 'quarter_number' => 2, 'week_number' => 1, 'day_of_week' => 3, 'sequence_order' => 1, 'lesson_video_url' => 'lesson-videos/old.mp4']);
        $hop = $this->practical($q2, 'Hop');
        $digital = DB::table('activities')->insertGetId(['lesson_id' => $q2, 'activity_code' => 'DG-1', 'name' => 'Game', 'instructions' => '-', 'activity_format' => 'digital', 'sequence_order' => 2]);
        $video = fn () => UploadedFile::fake()->create('clip.mp4', 512, 'video/mp4');
        $payload = ['name' => 'Jump', 'description' => 'Jumping.', 'instructions' => 'Jump twice.', 'teacherGuidelines' => 'Two jumps.'];
        $locked = ['quarter' => 'The 2nd Quarter is active and open for classes, so its lessons and practical activities are read-only.'];
        $as = $this->actingAs($tina);

        // Q2 is open for classes: nothing in it can be added, changed or removed, apart from a first video
        // (test_active_quarter_allows_only_a_first_video).
        $lessonCount = DB::table('lessons')->count();
        $as->post("/api/modules/{$reading}/lessons", ['moduleDomainId' => $domain, 'title' => 'New', 'quarter' => 2, 'week' => 2, 'day' => 1, 'video' => $video()], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors($locked);
        $as->patchJson("/api/lessons/{$q2}", ['title' => 'Renamed', 'description' => 'Changed'])->assertStatus(422)->assertJsonValidationErrors($locked);
        $as->post("/api/lessons/{$q2Video}/video", ['video' => $video()], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('video');
        $as->deleteJson("/api/lessons/{$q2Video}/video")->assertStatus(422)->assertJsonValidationErrors($locked);
        $as->post("/api/lessons/{$q2}/practical-activities", $payload + ['video' => $video()], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors($locked);
        $as->post("/api/practical-activities/{$hop}", $payload + ['video' => $video()], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors($locked);
        $as->deleteJson("/api/practical-activities/{$hop}")->assertStatus(422)->assertJsonValidationErrors($locked);

        $this->assertSame($lessonCount, DB::table('lessons')->count());
        $this->assertSame(['Lesson Q2', null], [DB::table('lessons')->where('id', $q2)->value('title'), DB::table('lessons')->where('id', $q2)->value('description')]);
        $this->assertSame('lesson-videos/old.mp4', DB::table('lessons')->where('id', $q2Video)->value('lesson_video_url'));
        $this->assertSame(['Hop', true], [DB::table('activities')->where('id', $hop)->value('name'), (bool) DB::table('activities')->where('id', $hop)->value('is_active')]);
        $this->assertSame(1, DB::table('activities')->where('lesson_id', $q2)->where('activity_format', 'practical')->count());
        // Rejected uploads leave no file behind, and the existing video stays.
        $this->assertSame(['lesson-videos/old.mp4'], $disk->allFiles());

        // Digital activities still can't be edited at all.
        $as->post("/api/practical-activities/{$digital}", $payload, ['Accept' => 'application/json'])->assertNotFound();
        $as->deleteJson("/api/practical-activities/{$digital}")->assertNotFound();

        // Q1 isn't active, so the teacher prepares it freely.
        $as->postJson("/api/modules/{$reading}/lessons", ['moduleDomainId' => $domain, 'title' => 'New', 'quarter' => 1, 'week' => 2, 'day' => 1])->assertCreated();
        $as->patchJson("/api/lessons/{$q1}", ['title' => 'Renamed'])->assertOk();
        $as->post("/api/lessons/{$q1}/video", ['video' => $video()], ['Accept' => 'application/json'])->assertOk();
        $as->deleteJson("/api/lessons/{$q1}/video")->assertOk();
        $jump = $as->postJson("/api/lessons/{$q1}/practical-activities", $payload)->assertCreated()->json('id');
        $as->postJson("/api/practical-activities/{$jump}", ['name' => 'Jump high'] + $payload)->assertOk();
        $as->deleteJson("/api/practical-activities/{$jump}")->assertOk();
    }

    public function test_active_quarter_allows_only_a_first_video(): void
    {
        [$tina, , $domain, , $q2] = $this->quarterTwoActive();
        $disk = Storage::fake('public');
        $video = fn () => UploadedFile::fake()->create('clip.mp4', 512, 'video/mp4');
        $payload = ['name' => 'Jump', 'description' => 'Jumping.', 'instructions' => 'Jump twice.', 'teacherGuidelines' => 'Two jumps.'];
        $locked = ['quarter' => 'The 2nd Quarter is active'];
        $as = $this->actingAs($tina);
        $lessonVideo = fn () => $as->post("/api/lessons/{$q2}/video", ['video' => $video()], ['Accept' => 'application/json']);

        // A learner already did one of the lesson's activities, so both have records; the lesson still gets its first video.
        $skip = $this->practical($q2, 'Skip');
        $this->oldYearRecord($skip, $tina);
        $lessonVideo()->assertOk()->assertJsonPath('lessonId', $q2);
        $path = DB::table('lessons')->where('id', $q2)->value('lesson_video_url');
        $disk->assertExists($path);

        // Once there, it stays: a second upload, removing it, and every other change are refused.
        $lessonVideo()->assertStatus(422)->assertJsonValidationErrors(['video' => 'This lesson already has a video.']);
        $as->deleteJson("/api/lessons/{$q2}/video")->assertStatus(422)->assertJsonValidationErrors($locked);
        $as->patchJson("/api/lessons/{$q2}", ['title' => 'Renamed', 'description' => 'Changed'])->assertStatus(422)->assertJsonValidationErrors($locked);
        $this->assertSame([$path, 'Lesson Q2', null], array_values((array) DB::table('lessons')->where('id', $q2)->first(['lesson_video_url', 'title', 'description'])));
        $this->assertSame([$path], $disk->allFiles());

        // A practical activity without a demo gets one, and nothing else about it changes.
        $hop = $this->practical($q2, 'Hop');
        $before = DB::table('activities')->where('id', $hop)->first(['name', 'description', 'instructions', 'is_active']);
        $attach = fn (int $id) => $as->post("/api/practical-activities/{$id}/video", ['video' => $video()], ['Accept' => 'application/json']);
        $attach($hop)->assertOk()->assertJson(['id' => $hop, 'activityName' => 'Hop', 'teacherGuidelines' => '-', 'demoVideoReady' => true]);
        $demo = DB::table('practical_activity_details')->where('activity_id', $hop)->value('demo_video_url');
        $disk->assertExists($demo);
        $this->assertEquals($before, DB::table('activities')->where('id', $hop)->first(['name', 'description', 'instructions', 'is_active']));
        $this->assertSame('-', DB::table('practical_activity_details')->where('activity_id', $hop)->value('teacher_guidelines'));

        // Its demo can't be replaced either way, or the activity edited or removed.
        $attach($hop)->assertStatus(422)->assertJsonValidationErrors(['video' => 'This practical activity already has a video.']);
        $as->post("/api/practical-activities/{$hop}", $payload + ['video' => $video()], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors($locked);
        $as->deleteJson("/api/practical-activities/{$hop}")->assertStatus(422)->assertJsonValidationErrors($locked);
        $this->assertSame($demo, DB::table('practical_activity_details')->where('activity_id', $hop)->value('demo_video_url'));
        $this->assertEqualsCanonicalizing([$path, $demo], $disk->allFiles());

        // So does the practical activity the learner already did.
        $attach($skip)->assertOk();

        // Only practical activities, and only real videos.
        $digital = DB::table('activities')->insertGetId(['lesson_id' => $q2, 'activity_code' => 'DG-1', 'name' => 'Game', 'instructions' => '-', 'activity_format' => 'digital', 'sequence_order' => 3]);
        $attach($digital)->assertNotFound();
        $attach(99999)->assertNotFound();
        $jump = $this->practical($q2, 'Jump');
        $as->post("/api/practical-activities/{$jump}/video", ['video' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('video');
        $this->assertNull(DB::table('practical_activity_details')->where('activity_id', $jump)->value('demo_video_url'));

        // Not a way to add lessons or activities to the active quarter.
        $as->post("/api/lessons/{$q2}/practical-activities", $payload + ['video' => $video()], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors($locked);
        $this->assertSame(4, DB::table('activities')->where('lesson_id', $q2)->count());
        $this->assertSame(1, DB::table('lessons')->where('module_domain_id', $domain)->where('quarter_number', 2)->count());
    }

    public function test_an_upcoming_quarter_keeps_the_record_rule_for_a_first_video(): void
    {
        [$tina, , , $q1] = $this->quarterTwoActive();
        Storage::fake('public');
        $attach = fn (int $id) => $this->actingAs($tina)->post("/api/practical-activities/{$id}/video", ['video' => UploadedFile::fake()->create('clip.mp4', 512, 'video/mp4')], ['Accept' => 'application/json']);

        $hop = $this->practical($q1, 'Hop');
        $attach($hop)->assertOk();
        $skip = $this->practical($q1, 'Skip');
        $this->oldYearRecord($skip, $tina);
        $attach($skip)->assertStatus(422)->assertJsonValidationErrors('protected');
        $this->assertNull(DB::table('practical_activity_details')->where('activity_id', $skip)->value('demo_video_url'));
    }

    public function test_video_attaches_take_the_quarter_lock(): void
    {
        [$tina, , , , $q2] = $this->quarterTwoActive();
        Storage::fake('public');
        $hop = $this->practical($q2, 'Hop');
        $video = fn () => UploadedFile::fake()->create('clip.mp4', 512, 'video/mp4');

        $this->assertTrue($this->quarterLockFree());
        $this->actingAs($tina)->post("/api/practical-activities/{$hop}/video", ['video' => $video()], ['Accept' => 'application/json'])->assertOk();
        $this->assertFalse($this->quarterLockFree());
        $this->actingAs($tina)->post("/api/lessons/{$q2}/video", ['video' => $video()], ['Accept' => 'application/json'])->assertOk();
        $this->assertFalse($this->quarterLockFree());
    }

    public function test_a_lesson_or_activity_cannot_be_moved_into_or_out_of_the_active_quarter(): void
    {
        [$tina, $reading, $domain, $q1, $q2] = $this->quarterTwoActive();
        $payload = ['name' => 'Jump', 'description' => 'Jumping.', 'instructions' => 'Jump twice.', 'teacherGuidelines' => 'Two jumps.'];
        $as = $this->actingAs($tina);

        // Out of Q2: still locked, and it stays in Q2.
        $as->patchJson("/api/lessons/{$q2}", ['title' => 'Moved', 'quarter' => 1, 'quarterId' => 1, 'quarter_number' => 1])->assertStatus(422)->assertJsonValidationErrors('quarter');
        $this->assertSame(2, (int) DB::table('lessons')->where('id', $q2)->value('quarter_number'));

        // Into Q2: a quarter in the body is ignored, so the Q1 lesson saves but stays in Q1.
        $as->patchJson("/api/lessons/{$q1}", ['title' => 'Renamed', 'quarter' => 2, 'quarterId' => 2, 'quarter_number' => 2])->assertOk();
        $this->assertSame(['Renamed', 1], [DB::table('lessons')->where('id', $q1)->value('title'), (int) DB::table('lessons')->where('id', $q1)->value('quarter_number')]);

        // A new lesson can't land in Q2 either.
        $as->postJson("/api/modules/{$reading}/lessons", ['moduleDomainId' => $domain, 'title' => 'New', 'quarter' => 2, 'week' => 1, 'day' => 1])->assertStatus(422)->assertJsonValidationErrors('quarter');

        // A practical activity keeps its lesson: Q1's can't be re-pointed at a Q2 lesson, and Q2's can't leave.
        $q1Hop = $this->practical($q1, 'Hop');
        $q2Hop = $this->practical($q2, 'Skip');
        $as->postJson("/api/practical-activities/{$q1Hop}", $payload + ['lessonId' => $q2, 'lesson_id' => $q2])->assertOk();
        $this->assertSame($q1, DB::table('activities')->where('id', $q1Hop)->value('lesson_id'));
        $as->postJson("/api/practical-activities/{$q2Hop}", $payload + ['lessonId' => $q1, 'lesson_id' => $q1])->assertStatus(422)->assertJsonValidationErrors('quarter');
        $this->assertSame($q2, DB::table('activities')->where('id', $q2Hop)->value('lesson_id'));
    }

    public function test_an_open_edit_is_rejected_once_its_quarter_is_activated(): void
    {
        [$tina, , , $q1] = $this->quarterTwoActive();
        $year = SchoolYear::active();

        $this->actingAs($tina)->patchJson("/api/lessons/{$q1}", ['title' => 'Before'])->assertOk();
        $this->actingAs($tina)->patchJson("/api/school-years/{$year->id}/quarters/1/activate")->assertOk();
        // The same form saved again after activation is checked against the new state.
        $this->actingAs($tina)->patchJson("/api/lessons/{$q1}", ['title' => 'After'])->assertStatus(422)->assertJsonValidationErrors(['quarter' => 'The 1st Quarter']);
        $this->assertSame('Before', DB::table('lessons')->where('id', $q1)->value('title'));
        // Q2 is no longer active, so it opens up.
        $this->actingAs($tina)->patchJson('/api/lessons/'.DB::table('lessons')->where('quarter_number', 2)->value('id'), ['title' => 'Now editable'])->assertOk();
    }

    public function test_only_upcoming_quarter_content_can_change_even_without_records(): void
    {
        [$tina, $reading, $domain, $q1] = $this->quarterTwoActive();
        $year = SchoolYear::active();
        DB::table('quarters')->where(['school_year_id' => $year->id, 'quarter_number' => 1])->update(['status' => 'completed']);
        DB::table('quarters')->insert(['school_year_id' => $year->id, 'quarter_number' => 3, 'name' => 'Q3', 'start_date' => '2027-01-01', 'end_date' => '2027-02-28', 'status' => 'upcoming']);
        $lesson = fn (int $quarter, string $title, ?string $video = null) => DB::table('lessons')->insertGetId(['module_domain_id' => $domain, 'title' => $title, 'quarter_number' => $quarter, 'week_number' => 1, 'day_of_week' => 2, 'sequence_order' => 1, 'lesson_video_url' => $video]);
        $disk = Storage::fake('public');
        $video = fn () => UploadedFile::fake()->create('clip.mp4', 512, 'video/mp4');
        $payload = ['name' => 'Jump', 'description' => 'Jumping.', 'instructions' => 'Jump twice.', 'teacherGuidelines' => 'Two jumps.'];
        $as = $this->actingAs($tina);

        // Completed Q1 and Q4, which the year hasn't set up: unused content, and still nothing in them can be added,
        // changed or removed. Rows stay as they were, existing videos stay, and rejected uploads leave nothing behind.
        $rejected = [
            1 => 'The 1st Quarter is completed, so its lessons and practical activities can no longer be changed.',
            4 => "The 4th Quarter isn't set up in the active school year, so its lessons and practical activities can't be changed yet. Set its dates in Manage Quarters first.",
        ];
        foreach ($rejected as $quarter => $message) {
            $plain = $quarter === 1 ? $q1 : $lesson($quarter, "Lesson Q{$quarter}");
            $disk->put("lesson-videos/q{$quarter}.mp4", 'x');
            $disk->put("practical-demos/q{$quarter}.mp4", 'x');
            $withVideo = $lesson($quarter, 'With video', "lesson-videos/q{$quarter}.mp4");
            $hop = $this->practical($plain, "Hop Q{$quarter}");
            DB::table('practical_activity_details')->where('activity_id', $hop)->update(['demo_video_url' => "practical-demos/q{$quarter}.mp4"]);
            $bare = $this->practical($plain, "Bare Q{$quarter}");
            $files = $disk->allFiles();
            $snapshot = fn () => [DB::table('lessons')->count(), DB::table('activities')->count(), $this->recordSnapshot([$plain, $withVideo], [$hop, $bare], [])];
            $before = $snapshot();
            $error = ['quarter' => $message];

            $as->post("/api/modules/{$reading}/lessons", ['moduleDomainId' => $domain, 'title' => 'New', 'quarter' => $quarter, 'week' => 2, 'day' => 1, 'video' => $video()], ['Accept' => 'application/json'])
                ->assertStatus(422)->assertJsonValidationErrors($error);
            $as->patchJson("/api/lessons/{$plain}", ['title' => 'Renamed', 'description' => 'Changed'])->assertStatus(422)->assertJsonValidationErrors($error);
            $as->post("/api/lessons/{$plain}/video", ['video' => $video()], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors($error);
            // Unlike the active quarter, not even a first video.
            $as->post("/api/practical-activities/{$bare}/video", ['video' => $video()], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors($error);
            $as->deleteJson("/api/lessons/{$withVideo}/video")->assertStatus(422)->assertJsonValidationErrors($error);
            $as->post("/api/lessons/{$plain}/practical-activities", $payload + ['video' => $video()], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors($error);
            $as->post("/api/practical-activities/{$hop}", $payload + ['video' => $video()], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors($error);
            $as->deleteJson("/api/practical-activities/{$hop}")->assertStatus(422)->assertJsonValidationErrors($error);

            $this->assertEquals($before, $snapshot());
            $this->assertEqualsCanonicalizing($files, $disk->allFiles());
        }

        // Still viewable, videos included, and the UI gets each configured quarter's status.
        $this->assertContains($q1, array_column(collect($as->getJson('/api/modules')->assertOk()->json())->firstWhere('id', $reading)['lessons'], 'id'));
        $week = collect($as->getJson("/api/modules/{$reading}/lessons?quarter=1&week=1")->assertOk()->json());
        $this->assertEqualsCanonicalizing(['Hop Q1', 'Bare Q1'], array_column($week->firstWhere('lessonId', $q1)['activities'], 'activityName'));
        $as->getJson('/api/school-years/active')->assertOk()
            ->assertJson(['quarterStatuses' => [1 => 'completed', 2 => 'active', 3 => 'upcoming']])->assertJsonMissingPath('quarterStatuses.4');

        // Upcoming Q3 is prepared as before.
        $id = $lesson(3, 'Lesson Q3');
        $as->postJson("/api/modules/{$reading}/lessons", ['moduleDomainId' => $domain, 'title' => 'New', 'quarter' => 3, 'week' => 2, 'day' => 1])->assertCreated();
        $as->patchJson("/api/lessons/{$id}", ['title' => 'Renamed'])->assertOk();
        $as->post("/api/lessons/{$id}/video", ['video' => $video()], ['Accept' => 'application/json'])->assertOk();
        $as->deleteJson("/api/lessons/{$id}/video")->assertOk();
        $jump = $as->postJson("/api/lessons/{$id}/practical-activities", $payload)->assertCreated()->json('id');
        $as->postJson("/api/practical-activities/{$jump}", ['name' => 'Jump high'] + $payload)->assertOk();
        $as->deleteJson("/api/practical-activities/{$jump}")->assertOk();
    }

    public function test_no_active_school_year_blocks_curriculum_changes(): void
    {
        [$tina, , , $q1] = $this->quarterTwoActive();
        SchoolYear::active()->update(['status' => 'completed']);
        $payload = ['name' => 'Jump', 'description' => 'Jumping.', 'instructions' => 'Jump twice.', 'teacherGuidelines' => 'Two jumps.'];

        // Q1 was upcoming in that year, but with no year active no quarter is being prepared.
        $this->actingAs($tina)->patchJson("/api/lessons/{$q1}", ['title' => 'Renamed'])->assertStatus(422)->assertJsonValidationErrors(['quarter' => "The 1st Quarter isn't set up"]);
        $this->actingAs($tina)->postJson("/api/lessons/{$q1}/practical-activities", $payload)->assertStatus(422)->assertJsonValidationErrors('quarter');
        $this->assertSame(['Lesson Q1', 0], [DB::table('lessons')->where('id', $q1)->value('title'), DB::table('activities')->where('lesson_id', $q1)->count()]);
    }

    public function test_an_open_edit_is_rejected_once_its_quarter_is_completed(): void
    {
        [$tina, , , $q1] = $this->quarterTwoActive();
        $year = SchoolYear::active();
        // After Q1's end date (2026-12-31), so leaving the active state completes it.
        Carbon::setTestNow('2027-01-15 09:00');

        $this->actingAs($tina)->patchJson("/api/lessons/{$q1}", ['title' => 'Before'])->assertOk();
        // While the form stays open, Q1 runs as the active quarter and then gives way to Q2.
        $this->actingAs($tina)->patchJson("/api/school-years/{$year->id}/quarters/1/activate")->assertOk();
        $this->actingAs($tina)->patchJson("/api/school-years/{$year->id}/quarters/2/activate")->assertOk();
        $this->assertSame('completed', DB::table('quarters')->where(['school_year_id' => $year->id, 'quarter_number' => 1])->value('status'));

        $this->actingAs($tina)->patchJson("/api/lessons/{$q1}", ['title' => 'After'])->assertStatus(422)->assertJsonValidationErrors(['quarter' => 'The 1st Quarter is completed']);
        $this->assertSame('Before', DB::table('lessons')->where('id', $q1)->value('title'));
    }

    public function test_lesson_edits_take_the_quarter_lock(): void
    {
        [$tina, , , $q1] = $this->quarterTwoActive();

        $this->assertTrue($this->quarterLockFree());
        $this->actingAs($tina)->patchJson("/api/lessons/{$q1}", ['title' => 'Renamed'])->assertOk();
        $this->assertFalse($this->quarterLockFree());
    }

    public function test_practical_activity_deletes_take_the_quarter_lock(): void
    {
        [$tina, , , $q1] = $this->quarterTwoActive();
        $hop = $this->practical($q1, 'Hop');

        $this->assertTrue($this->quarterLockFree());
        $this->actingAs($tina)->deleteJson("/api/practical-activities/{$hop}")->assertOk();
        $this->assertFalse($this->quarterLockFree());
    }

    /**
     * A finished 2025-2026 with one enrolled learner in an old section, who played $activity there and was rated.
     *
     * @return array{int, int} the old enrollment and its Q1 quarter
     */
    private function oldYearRecord(int $activity, Teacher $teacher): array
    {
        $old = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-06-01', 'end_date' => '2026-03-31', 'status' => 'completed']);
        $quarter = DB::table('quarters')->insertGetId(['school_year_id' => $old->id, 'quarter_number' => 1, 'name' => 'Q1', 'start_date' => '2025-06-01', 'end_date' => '2025-08-31', 'status' => 'completed']);
        $section = DB::table('sections')->insertGetId(['school_year_id' => $old->id, 'teacher_id' => $teacher->id, 'name' => 'Hope', 'grade_level' => 'Kindergarten']);
        $enrollment = DB::table('learner_school_years')->insertGetId([
            'learner_id' => DB::table('learners')->insertGetId(['first_name' => 'Old', 'last_name' => 'Timer', 'birth_date' => '2020-01-01']),
            'school_year_id' => $old->id, 'section_id' => $section, 'enrolled_on' => '2025-06-01', 'status' => 'completed',
        ]);
        $assignment = DB::table('learner_activity_assignments')->insertGetId([
            'enrollment_id' => $enrollment, 'activity_id' => $activity, 'quarter_id' => $quarter, 'teacher_id' => $teacher->id,
            'assigned_at' => '2025-07-01 08:00', 'status' => 'completed', 'completed_at' => '2025-07-01 09:00',
        ]);
        DB::table('learner_activity_attempts')->insert([
            'assignment_id' => $assignment, 'attempt_number' => 1, 'status' => 'evaluated', 'practical_rating' => 'good',
            'evaluated_by' => $teacher->id, 'started_at' => '2025-07-01 08:00', 'evaluated_at' => '2025-07-01 09:00',
        ]);

        return [$enrollment, $quarter];
    }

    // Every row a protected lesson, practical activity or checklist item and its learner records are made of.
    private function recordSnapshot(array $lessons, array $activities, array $items): array
    {
        return [
            DB::table('lessons')->whereIn('id', $lessons)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('activities')->whereIn('id', $activities)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('practical_activity_details')->whereIn('activity_id', $activities)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('developmental_checklist_items')->whereIn('id', $items)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('learner_activity_assignments')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('learner_activity_attempts')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('learner_developmental_checklists')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    public function test_content_with_records_stays_protected_while_unused_content_stays_editable(): void
    {
        [$tina, $reading, $domain, $used, $q2] = $this->quarterTwoActive();
        $joy = $this->setUpSections($tina, ['Joy'])['Joy'];
        $disk = Storage::fake('public');
        $disk->put('practical-demos/hop.mp4', 'x');
        $skillDomain = DB::table('module_domains')->where('id', $domain)->value('domain_id');
        $free = DB::table('lessons')->insertGetId(['module_domain_id' => $domain, 'title' => 'Free', 'quarter_number' => 1, 'week_number' => 1, 'day_of_week' => 3, 'sequence_order' => 1]);
        $hop = $this->practical($used, 'Hop');
        DB::table('practical_activity_details')->where('activity_id', $hop)->update(['demo_video_url' => 'practical-demos/hop.mp4']);
        $skip = $this->practical($free, 'Skip');
        [$oldEnrollment, $oldQuarter] = $this->oldYearRecord($hop, $tina);
        $item = fn (string $name, ?int $activity) => DB::table('developmental_checklist_items')->insertGetId(['domain_id' => $skillDomain, 'item' => $name, 'grade_level' => 'Kindergarten', 'quarter_number' => 1, 'activity_id' => $activity, 'display_order' => 1]);
        $linked = $item('Hops', $hop);
        $rated = $item('Balances', null);
        $plain = $item('Skips', null);
        DB::table('learner_developmental_checklists')->insert(['enrollment_id' => $oldEnrollment, 'checklist_item_id' => $rated, 'quarter_id' => $oldQuarter, 'teacher_id' => $tina->id, 'rating' => 'achieved', 'assessed_at' => '2025-07-02']);

        $video = fn () => UploadedFile::fake()->create('clip.mp4', 512, 'video/mp4');
        $payload = ['name' => 'Jump', 'description' => 'Jumping.', 'instructions' => 'Jump twice.', 'teacherGuidelines' => 'Two jumps.'];
        $protected = ['protected' => 'This content has classroom or learner records and can no longer be changed.'];
        $as = $this->actingAs($tina);
        $before = $this->recordSnapshot([$used], [$hop], [$linked, $rated]);

        // The API says which content has records, so the board and checklist can lock just those.
        $board = collect($as->getJson('/api/modules')->json())->firstWhere('id', $reading)['lessons'];
        $this->assertSame([$used => true, $free => false, $q2 => false], collect($board)->whereIn('id', [$used, $free, $q2])->pluck('hasRecords', 'id')->all());
        $week = collect($as->getJson("/api/modules/{$reading}/lessons?quarter=1&week=1")->json());
        $this->assertSame([$used => true, $free => false], $week->pluck('hasRecords', 'lessonId')->all());
        $this->assertSame([$hop => true, $skip => false], $week->pluck('activities')->collapse()->pluck('hasRecords', 'id')->all());
        $checklist = collect($as->getJson("/api/sections/{$joy}/checklist")->json('domains'))->firstWhere('domainId', $skillDomain);
        $this->assertSame([$linked => true, $rated => true, $plain => false], collect($checklist['items'])->pluck('hasRecords', 'itemId')->all());
        $this->assertTrue(collect($checklist['activities'])->firstWhere('activityId', $hop)['hasRecords']);

        // Played in an old year's section, Q1 isn't active, and still: nothing about that content changes.
        $as->patchJson("/api/lessons/{$used}", ['title' => 'Renamed', 'description' => 'New'])->assertStatus(422)->assertJsonValidationErrors($protected);
        $as->patchJson("/api/lessons/{$used}", ['title' => 'Moved', 'quarter' => 3, 'quarterId' => 3, 'week' => 4])->assertStatus(422)->assertJsonValidationErrors('protected');
        $as->post("/api/lessons/{$used}/video", ['video' => $video()], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors($protected);
        $as->deleteJson("/api/lessons/{$used}/video")->assertStatus(422)->assertJsonValidationErrors($protected);
        $as->post("/api/practical-activities/{$hop}", $payload + ['video' => $video(), 'lessonId' => $free], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors($protected);
        $as->deleteJson("/api/practical-activities/{$hop}")->assertStatus(422)->assertJsonValidationErrors($protected);
        $as->putJson("/api/checklist-items/{$linked}", ['item' => 'Hops far', 'activityId' => null])->assertStatus(422)->assertJsonValidationErrors($protected);
        $as->putJson("/api/checklist-items/{$rated}", ['item' => 'Balances long'])->assertStatus(422)->assertJsonValidationErrors($protected);

        // Rows, links and learner records are untouched; the old demo stays and rejected uploads left nothing behind.
        $this->assertEquals($before, $this->recordSnapshot([$used], [$hop], [$linked, $rated]));
        $this->assertSame(['practical-demos/hop.mp4'], $disk->allFiles());

        // The rest of Q1 is still being prepared: only content with records is locked.
        $as->patchJson("/api/lessons/{$free}", ['title' => 'Free renamed'])->assertOk();
        $as->post("/api/lessons/{$free}/video", ['video' => $video()], ['Accept' => 'application/json'])->assertOk();
        $as->deleteJson("/api/lessons/{$free}/video")->assertOk();
        $as->postJson("/api/practical-activities/{$skip}", ['name' => 'Skip rope'] + $payload)->assertOk();
        $as->deleteJson("/api/practical-activities/{$skip}")->assertOk();
        $as->putJson("/api/checklist-items/{$plain}", ['item' => 'Skips twice'])->assertOk()->assertJson(['hasRecords' => false]);

        // New content can join a used lesson's week and the used lesson itself, without touching what's protected.
        $as->postJson("/api/modules/{$reading}/lessons", ['moduleDomainId' => $domain, 'title' => 'Next', 'quarter' => 1, 'week' => 1, 'day' => 5])->assertCreated()->assertJson(['hasRecords' => false]);
        $as->postJson("/api/lessons/{$used}/practical-activities", $payload)->assertCreated()->assertJson(['hasRecords' => false]);
        $as->postJson('/api/checklist-items', ['domainId' => $skillDomain, 'quarter' => 1, 'item' => 'Jumps'])->assertCreated();
        $this->assertEquals($before, $this->recordSnapshot([$used], [$hop], [$linked, $rated]));

        // In the active quarter its own reason comes first, records or not.
        DB::table('learner_activity_assignments')->insert(['enrollment_id' => $oldEnrollment, 'activity_id' => $this->practical($q2, 'Q2 game'), 'quarter_id' => $oldQuarter, 'teacher_id' => $tina->id, 'assigned_at' => '2025-07-03 08:00', 'status' => 'assigned']);
        $as->patchJson("/api/lessons/{$q2}", ['title' => 'x'])->assertStatus(422)->assertJsonValidationErrors('quarter')->assertJsonMissingValidationErrors('protected');
    }

    public function test_a_skill_cannot_be_linked_to_an_activity_with_records(): void
    {
        [$tina, , $domain, $used] = $this->quarterTwoActive();
        $skillDomain = DB::table('module_domains')->where('id', $domain)->value('domain_id');
        $hop = $this->practical($used, 'Hop');
        $skip = $this->practical($used, 'Skip');
        $this->oldYearRecord($hop, $tina);
        $add = fn (array $body) => $this->actingAs($tina)->postJson('/api/checklist-items', $body + ['domainId' => $skillDomain, 'quarter' => 1]);
        $linkError = ['activityId' => 'linking a skill to it would change past results and reports'];

        // Its old results would become this skill's evidence on the checklist and in progress reports.
        $add(['item' => 'Hops', 'activityId' => $hop])->assertStatus(422)->assertJsonValidationErrors($linkError);
        $this->assertSame(0, DB::table('developmental_checklist_items')->count());

        $unlinked = $add(['item' => 'Hops'])->assertCreated()->json('itemId');
        $add(['item' => 'Skips', 'activityId' => $skip])->assertCreated();
        $this->actingAs($tina)->putJson("/api/checklist-items/{$unlinked}", ['item' => 'Hops', 'activityId' => $hop])->assertStatus(422)->assertJsonValidationErrors($linkError);
        $this->assertNull(DB::table('developmental_checklist_items')->where('id', $unlinked)->value('activity_id'));
    }

    public function test_content_used_after_a_form_opened_is_protected_on_save(): void
    {
        [$tina, , , $q1] = $this->quarterTwoActive();
        $hop = $this->practical($q1, 'Hop');
        $payload = ['name' => 'Jump', 'description' => 'Jumping.', 'instructions' => 'Jump twice.', 'teacherGuidelines' => 'Two jumps.'];

        $this->actingAs($tina)->patchJson("/api/lessons/{$q1}", ['title' => 'Before'])->assertOk();
        $this->actingAs($tina)->postJson("/api/practical-activities/{$hop}", $payload)->assertOk();
        // A learner record lands while the teacher's edit forms are still open.
        $this->oldYearRecord($hop, $tina);
        $this->actingAs($tina)->patchJson("/api/lessons/{$q1}", ['title' => 'After'])->assertStatus(422)->assertJsonValidationErrors('protected');
        $this->actingAs($tina)->postJson("/api/practical-activities/{$hop}", ['name' => 'Changed'] + $payload)->assertStatus(422)->assertJsonValidationErrors('protected');
        $this->assertSame(['Before', 'Jump'], [DB::table('lessons')->where('id', $q1)->value('title'), DB::table('activities')->where('id', $hop)->value('name')]);
    }

    public function test_checklist_item_edits_take_the_quarter_lock(): void
    {
        [$tina, , $domain] = $this->quarterTwoActive();
        $id = DB::table('developmental_checklist_items')->insertGetId(['domain_id' => DB::table('module_domains')->where('id', $domain)->value('domain_id'), 'item' => 'Hops', 'grade_level' => 'Kindergarten', 'quarter_number' => 1, 'display_order' => 1]);

        $this->assertTrue($this->quarterLockFree());
        $this->actingAs($tina)->putJson("/api/checklist-items/{$id}", ['item' => 'Hops far'])->assertOk();
        $this->assertFalse($this->quarterLockFree());
    }

    public function test_checklist_ratings_take_the_quarter_lock(): void
    {
        [$tina, , $domain] = $this->quarterTwoActive();
        $joy = $this->setUpSections($tina, ['Joy'])['Joy'];
        $year = SchoolYear::active();
        $enrollment = DB::table('learner_school_years')->insertGetId([
            'learner_id' => DB::table('learners')->insertGetId(['first_name' => 'Ana', 'last_name' => 'Cruz', 'birth_date' => '2021-01-01']),
            'school_year_id' => $year->id, 'section_id' => $joy, 'enrolled_on' => '2026-06-01', 'status' => 'active',
        ]);
        $id = DB::table('developmental_checklist_items')->insertGetId(['domain_id' => DB::table('module_domains')->where('id', $domain)->value('domain_id'), 'item' => 'Hops', 'grade_level' => 'Kindergarten', 'quarter_number' => 1, 'display_order' => 1]);

        $this->assertTrue($this->quarterLockFree());
        $this->actingAs($tina)->putJson("/api/sections/{$joy}/checklist/ratings", ['enrollmentId' => $enrollment, 'quarter' => 1, 'itemId' => $id, 'rating' => 'achieved'])->assertOk();
        $this->assertFalse($this->quarterLockFree());
    }
}

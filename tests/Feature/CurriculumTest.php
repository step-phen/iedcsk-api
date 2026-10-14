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
        $this->assertSame([['itemId' => $item, 'item' => 'Counts to 10', 'quarter' => 2, 'activityId' => $counting, 'activityName' => 'Count the Apples']], collect($data['domains'])->firstWhere('domainId', $math)['items']);
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
        $this->assertSame([['activityId' => $counting, 'name' => 'Count the Apples', 'quarter' => 1]], $domain['activities']);

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
        SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
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

        // It shows on the board and takes lessons like any other module.
        $board = collect($this->actingAs($tina)->getJson("/api/modules?section={$joy}")->assertOk()->json())->firstWhere('id', $created['id']);
        $this->assertSame('Science Explorers', $board['moduleName']);
        $this->actingAs($tina)->postJson("/api/modules/{$created['id']}/lessons", [
            'moduleDomainId' => $created['domains'][1]['id'], 'title' => 'Plants', 'quarter' => 1, 'week' => 1, 'day' => 2,
        ])->assertCreated();

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

    public function test_teacher_adds_lesson_to_module_domain(): void
    {
        $this->seed(CurriculumSeeder::class);
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
}

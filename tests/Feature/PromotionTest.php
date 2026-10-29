<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $tina;

    private Teacher $omar;

    /** @var array<int, int> checklist item id per quarter number */
    private array $items = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tina = Teacher::create(['username' => 'tina', 'name' => 'Tina Santos', 'password_hash' => 'x']);
        $this->omar = Teacher::create(['username' => 'omar', 'name' => 'Omar Reyes', 'password_hash' => 'x']);
        $domain = DB::table('developmental_domains')->insertGetId(['code' => 'math', 'name' => 'Math', 'display_order' => 1]);
        foreach ([1, 2, 3, 4] as $q) {
            $this->items[$q] = DB::table('developmental_checklist_items')->insertGetId(['domain_id' => $domain, 'item' => "Skill Q{$q}", 'grade_level' => 'Kindergarten', 'quarter_number' => $q, 'display_order' => 1]);
        }
    }

    private function year(string $name, string $status, ?string $endDate = '2026-03-31'): SchoolYear
    {
        $start = substr($name, 0, 4);
        $year = SchoolYear::create(['name' => $name, 'start_date' => $endDate ? "{$start}-06-01" : null, 'end_date' => $endDate, 'status' => $status]);
        foreach ([1, 2, 3, 4] as $n) {
            DB::table('quarters')->insert(['school_year_id' => $year->id, 'quarter_number' => $n, 'name' => "Q{$n}", 'start_date' => sprintf('%s-%02d-01', $start, 5 + $n), 'end_date' => sprintf('%s-%02d-28', $start, 5 + $n), 'status' => 'upcoming']);
        }

        return $year;
    }

    private function section(SchoolYear $year, Teacher $teacher, string $name = 'Joy'): Section
    {
        return Section::create(['school_year_id' => $year->id, 'teacher_id' => $teacher->id, 'grade_level' => 'Kindergarten', 'name' => $name]);
    }

    // Enrolls the learner and rates every quarter's skill with $rating in that section's year.
    private function enroll(Learner $learner, Section $section, string $rating, string $status = 'completed'): void
    {
        $enrollment = $learner->enrollments()->create(['school_year_id' => $section->school_year_id, 'section_id' => $section->id, 'enrolled_on' => '2025-06-01', 'status' => $status]);
        foreach ($this->items as $q => $item) {
            DB::table('learner_developmental_checklists')->insert([
                'enrollment_id' => $enrollment->id,
                'checklist_item_id' => $item,
                'quarter_id' => DB::table('quarters')->where(['school_year_id' => $section->school_year_id, 'quarter_number' => $q])->value('id'),
                'teacher_id' => $section->teacher_id,
                'rating' => $rating,
                'assessed_at' => now(),
            ]);
        }
    }

    private function learner(string $first): Learner
    {
        return Learner::create(['first_name' => $first, 'last_name' => 'Cruz', 'birth_date' => '2020-01-01', 'gender' => 'Female']);
    }

    public function test_lists_completed_years_with_their_own_promoted_count(): void
    {
        $past = $this->year('2025-2026', 'completed');
        $empty = $this->year('2024-2025', 'completed', '2025-03-31');
        $this->year('2026-2027', 'active', '2027-03-31');
        $joy = $this->section($past, $this->tina);
        $faith = $this->section($past, $this->tina, 'Faith');
        $this->section($empty, $this->tina);

        $this->enroll($this->learner('Ana'), $joy, 'achieved');
        $this->enroll($this->learner('Ben'), $faith, 'developing');
        $this->enroll($this->learner('Cara'), $joy, 'needs_support');
        $this->enroll($this->learner('Dan'), $joy, 'achieved', 'withdrawn');

        $this->actingAs($this->tina)->getJson('/api/promotions')->assertOk()->assertExactJson([
            ['schoolYearId' => $past->id, 'name' => '2025-2026', 'endDate' => '2026-03-31', 'adviser' => 'Tina Santos', 'promotedCount' => 2],
            ['schoolYearId' => $empty->id, 'name' => '2024-2025', 'endDate' => '2025-03-31', 'adviser' => 'Tina Santos', 'promotedCount' => 0],
        ]);

        $learners = $this->actingAs($this->tina)->getJson("/api/promotions/{$past->id}")->assertOk()->json('learners');
        $this->assertSame(['Ana', 'Ben'], array_column($learners, 'firstName'));
        $this->assertSame(['Joy', 'Faith'], array_column($learners, 'section'));
        $this->actingAs($this->tina)->getJson("/api/promotions/{$empty->id}")->assertOk()->assertJsonPath('learners', []);
    }

    public function test_reads_the_historical_year_not_the_current_one(): void
    {
        $past = $this->year('2025-2026', 'completed');
        $current = $this->year('2026-2027', 'active', '2027-03-31');
        $eva = $this->learner('Eva');
        // Repeating kindergarten: not ready last year, fully rated this year.
        $this->enroll($eva, $this->section($past, $this->tina), 'needs_support');
        $this->enroll($eva, $this->section($current, $this->tina), 'achieved', 'active');

        $this->actingAs($this->tina)->getJson('/api/promotions')->assertOk()->assertJsonPath('0.promotedCount', 0);
        $this->actingAs($this->tina)->getJson("/api/promotions/{$past->id}")->assertOk()->assertJsonPath('learners', []);
        $this->assertSame(['Eva'], array_column($this->actingAs($this->tina)->getJson('/api/learners?ready=1')->json(), 'firstName'));
    }

    public function test_hides_other_teachers_years_and_learners(): void
    {
        $past = $this->year('2025-2026', 'completed');
        $this->enroll($this->learner('Ana'), $this->section($past, $this->tina), 'achieved');
        $this->enroll($this->learner('Ben'), $this->section($past, $this->omar, 'Faith'), 'achieved');
        $omarsOnly = $this->year('2024-2025', 'completed', '2025-03-31');
        $this->section($omarsOnly, $this->omar);

        $this->actingAs($this->tina)->getJson('/api/promotions')->assertOk()->assertJsonCount(1)->assertJsonPath('0.promotedCount', 1);
        $this->assertSame(['Ana'], array_column($this->actingAs($this->tina)->getJson("/api/promotions/{$past->id}")->json('learners'), 'firstName'));
        $this->actingAs($this->tina)->getJson("/api/promotions/{$omarsOnly->id}")->assertNotFound();
    }

    // school_years_dated_unless_upcoming keeps a completed year from losing its dates, so an undated
    // year is still upcoming: it is left out like the active one, and the page shows "—" if a date is ever null.
    public function test_undated_and_unfinished_years_are_not_found(): void
    {
        $undated = $this->year('2027-2028', 'upcoming', null);
        $this->section($undated, $this->tina);
        $active = $this->year('2026-2027', 'active', '2027-03-31');
        $this->section($active, $this->tina);

        $this->actingAs($this->tina)->getJson('/api/promotions')->assertOk()->assertExactJson([]);
        $this->actingAs($this->tina)->getJson("/api/promotions/{$undated->id}")->assertNotFound();
        $this->actingAs($this->tina)->getJson("/api/promotions/{$active->id}")->assertNotFound();
        $this->actingAs($this->tina)->getJson('/api/promotions/999999')->assertNotFound();
    }

    public function test_requires_login(): void
    {
        $this->getJson('/api/promotions')->assertUnauthorized();
        $this->getJson('/api/promotions/1')->assertUnauthorized();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\LearnerSchoolYear;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $tina;

    private SchoolYear $past;

    private SchoolYear $active;

    private Section $pastJoy;

    /** @var array<int, int> checklist item id per quarter number */
    private array $items = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tina = Teacher::create(['username' => 'tina', 'name' => 'Tina Santos', 'password_hash' => 'x']);
        $domain = DB::table('developmental_domains')->insertGetId(['code' => 'math', 'name' => 'Math', 'display_order' => 1]);
        foreach ([1, 2, 3, 4] as $q) {
            $this->items[$q] = DB::table('developmental_checklist_items')->insertGetId(['domain_id' => $domain, 'item' => "Skill Q{$q}", 'grade_level' => 'Kindergarten', 'quarter_number' => $q, 'display_order' => 1]);
        }
        $this->past = $this->year('2025-2026', 'completed');
        $this->active = $this->year('2026-2027', 'active');
        $this->pastJoy = $this->section($this->past, $this->tina, 'Joy');
        $this->section($this->active, $this->tina, 'Joy');
        $this->section($this->active, $this->tina, 'Faith');
    }

    private function year(string $name, string $status): SchoolYear
    {
        $start = (int) substr($name, 0, 4);
        $year = SchoolYear::create(['name' => $name, 'start_date' => "{$start}-06-01", 'end_date' => ($start + 1).'-03-31', 'status' => $status]);
        foreach ([1, 2, 3, 4] as $n) {
            DB::table('quarters')->insert(['school_year_id' => $year->id, 'quarter_number' => $n, 'name' => "Q{$n}", 'start_date' => sprintf('%d-%02d-01', $start, 5 + $n), 'end_date' => sprintf('%d-%02d-28', $start, 5 + $n), 'status' => 'upcoming']);
        }

        return $year;
    }

    private function section(SchoolYear $year, Teacher $teacher, string $name): Section
    {
        return Section::create(['school_year_id' => $year->id, 'teacher_id' => $teacher->id, 'grade_level' => 'Kindergarten', 'name' => $name, 'choice' => Section::choiceFromName($name)]);
    }

    private function learner(string $first = 'Ana', int $age = 5, ?string $lrn = null): Learner
    {
        return Learner::create(['lrn' => $lrn, 'first_name' => $first, 'last_name' => 'Cruz', 'birth_date' => Carbon::now('Asia/Manila')->subYears($age)->subMonth()->toDateString(), 'gender' => 'Female']);
    }

    // An enrollment in $section's year with every quarter's skill rated $rating, one attendance day and one progress report.
    private function enrollWithRecords(Learner $learner, Section $section, string $status, string $rating): LearnerSchoolYear
    {
        $enrollment = $learner->enrollments()->create(['school_year_id' => $section->school_year_id, 'section_id' => $section->id, 'enrolled_on' => '2025-06-01', 'status' => $status, 'withdrawn_on' => $status === 'withdrawn' ? '2025-09-01' : null]);
        $quarterId = fn (int $q) => DB::table('quarters')->where(['school_year_id' => $section->school_year_id, 'quarter_number' => $q])->value('id');
        foreach ($this->items as $q => $item) {
            DB::table('learner_developmental_checklists')->insert(['enrollment_id' => $enrollment->id, 'checklist_item_id' => $item, 'quarter_id' => $quarterId($q), 'teacher_id' => $section->teacher_id, 'rating' => $rating, 'assessed_at' => '2025-08-01 08:00:00+08']);
        }
        DB::table('attendance')->insert(['enrollment_id' => $enrollment->id, 'attendance_date' => '2025-06-02', 'status' => 'present', 'recorded_by' => $section->teacher_id, 'recorded_at' => '2025-06-02 08:00:00+08']);
        DB::table('quarterly_progress_reports')->insert(['enrollment_id' => $enrollment->id, 'quarter_id' => $quarterId(1), 'teacher_id' => $section->teacher_id, 'status' => 'draft', 'generated_at' => '2025-08-30 08:00:00+08']);

        return $enrollment;
    }

    private function payload(array $overrides = []): array
    {
        return [...['gradeLevel' => 'Kindergarten', 'section' => 'Joy', 'schoolYear' => '2026-2027'], ...$overrides];
    }

    /** Every row tied to these enrollments, as stored. */
    private function snapshot(array $enrollmentIds): array
    {
        return collect(['learner_school_years' => 'id', 'learner_developmental_checklists' => 'enrollment_id', 'attendance' => 'enrollment_id', 'quarterly_progress_reports' => 'enrollment_id', 'learner_activity_assignments' => 'enrollment_id'])
            ->map(fn ($column, $table) => DB::table($table)->whereIn($column, $enrollmentIds)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all())
            ->all();
    }

    public function test_re_enrolls_a_withdrawn_learner_on_the_same_profile_and_leaves_history_untouched(): void
    {
        $ana = $this->learner();
        // Fully rated, but a withdrawn year never counts as Ready for Grade 1.
        $old = $this->enrollWithRecords($ana, $this->pastJoy, 'withdrawn', 'achieved');
        $before = $this->snapshot([$old->id]);

        $res = $this->actingAs($this->tina)->postJson("/api/learners/{$ana->id}/enrollments", $this->payload(['section' => 'Faith']))->assertCreated();

        $this->assertSame(1, Learner::count());
        $this->assertSame($ana->id, $res->json('learnerId'));
        $new = LearnerSchoolYear::findOrFail($res->json('enrollmentId'));
        $this->assertSame([$ana->id, $this->active->id, 'active', 'Faith'], [$new->learner_id, $new->school_year_id, $new->status, $new->section->name]);
        $this->assertSame(2, $ana->enrollments()->count());
        $this->assertEquals($before, $this->snapshot([$old->id]));

        // The new year starts empty: nothing is copied over.
        foreach (['learner_developmental_checklists', 'attendance', 'quarterly_progress_reports', 'learner_activity_assignments'] as $table) {
            $this->assertSame(0, DB::table($table)->where('enrollment_id', $new->id)->count(), $table);
        }
        // The profile now reads the new enrollment, with no ratings and no attendance.
        $profile = $this->actingAs($this->tina)->getJson("/api/learners/{$ana->id}")->assertOk();
        $profile->assertJsonPath('schoolYear', '2026-2027')->assertJsonPath('section', 'Faith')->assertJsonPath('attendances', []);
        $this->assertSame([null], collect($profile->json('progress'))->pluck('rating')->unique()->values()->all());
    }

    public function test_a_learner_not_ready_for_grade_1_may_repeat_kindergarten(): void
    {
        $ana = $this->learner();
        $this->enrollWithRecords($ana, $this->pastJoy, 'active', 'needs_support');

        $this->actingAs($this->tina)->postJson("/api/learners/{$ana->id}/enrollments", $this->payload())->assertCreated();
    }

    public function test_rejects_a_learner_who_met_ready_for_grade_1(): void
    {
        $ana = $this->learner();
        // Status stays active after the year completes; the ratings decide, not the status.
        $old = $this->enrollWithRecords($ana, $this->pastJoy, 'active', 'developing');
        $before = $this->snapshot([$old->id]);

        $this->actingAs($this->tina)->postJson("/api/learners/{$ana->id}/enrollments", $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors(['learner' => 'Ready for Grade 1 in SY 2025-2026']);

        $this->assertSame(1, $ana->enrollments()->count());
        $this->assertEquals($before, $this->snapshot([$old->id]));
    }

    public function test_rejects_a_second_enrollment_in_the_same_year(): void
    {
        $ana = $this->learner();
        $this->enrollWithRecords($ana, $this->pastJoy, 'withdrawn', 'needs_support');
        $this->actingAs($this->tina)->postJson("/api/learners/{$ana->id}/enrollments", $this->payload())->assertCreated();

        // A double submit, or a pick of the other section, is refused alike.
        $this->actingAs($this->tina)->postJson("/api/learners/{$ana->id}/enrollments", $this->payload(['section' => 'Faith']))
            ->assertStatus(422)->assertJsonValidationErrors(['learner' => 'Already enrolled in SY 2026-2027.']);
        $this->assertSame(1, $ana->enrollments()->where('school_year_id', $this->active->id)->count());
    }

    public function test_rejects_a_year_that_does_not_come_after_the_learners_enrollments(): void
    {
        // Enrolled in a later year that was activated and then switched back.
        $later = $this->year('2027-2028', 'upcoming');
        $ana = $this->learner();
        $ana->enrollments()->create(['school_year_id' => $later->id, 'section_id' => $this->section($later, $this->tina, 'Joy')->id, 'enrolled_on' => '2027-06-01', 'status' => 'withdrawn', 'withdrawn_on' => '2027-07-01']);

        $this->actingAs($this->tina)->postJson("/api/learners/{$ana->id}/enrollments", $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors(['learner' => 'does not come before SY 2026-2027']);
    }

    public function test_applies_the_add_learner_age_window(): void
    {
        $ana = $this->learner(age: 7);
        $this->enrollWithRecords($ana, $this->pastJoy, 'active', 'needs_support');

        $this->actingAs($this->tina)->postJson("/api/learners/{$ana->id}/enrollments", $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors(['learner' => 'Learner must be between 4 and 6 years old.']);
        $this->assertSame(1, $ana->enrollments()->count());
    }

    public function test_rejects_invalid_school_year_and_sections(): void
    {
        $ana = $this->learner();
        $this->enrollWithRecords($ana, $this->pastJoy, 'withdrawn', 'needs_support');
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->section($this->active, $omar, 'Hope');
        $url = "/api/learners/{$ana->id}/enrollments";

        // The past year by name, a section that is not this teacher's, or a made-up section.
        $this->actingAs($this->tina)->postJson($url, $this->payload(['schoolYear' => '2025-2026']))->assertStatus(422)->assertJsonValidationErrors('schoolYear');
        $this->actingAs($this->tina)->postJson($url, $this->payload(['section' => 'Hope']))->assertStatus(422)->assertJsonValidationErrors('section');
        $this->actingAs($this->tina)->postJson($url, $this->payload(['section' => 'Nope']))->assertStatus(422)->assertJsonValidationErrors('section');
        $this->actingAs($this->tina)->postJson($url, $this->payload(['gradeLevel' => 'Grade 1']))->assertStatus(422)->assertJsonValidationErrors('gradeLevel');

        // No active year at all.
        $this->active->update(['status' => 'completed']);
        $this->actingAs($this->tina)->postJson($url, $this->payload())->assertStatus(422)->assertJsonValidationErrors(['schoolYear', 'section']);

        $this->assertSame(1, $ana->enrollments()->count());
    }

    public function test_refuses_once_every_section_is_full(): void
    {
        $ana = $this->learner();
        $this->enrollWithRecords($ana, $this->pastJoy, 'withdrawn', 'needs_support');
        foreach (Section::where('school_year_id', $this->active->id)->get() as $section) {
            for ($i = 0; $i < Section::CAPACITY; $i++) {
                $this->learner("L{$section->name}{$i}")->enrollments()->create(['school_year_id' => $this->active->id, 'section_id' => $section->id, 'enrolled_on' => '2026-06-01', 'status' => 'active']);
            }
        }

        $this->actingAs($this->tina)->postJson("/api/learners/{$ana->id}/enrollments", $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('section');
    }

    public function test_only_the_teacher_who_taught_the_learner_may_find_or_re_enroll_them(): void
    {
        $ana = $this->learner();
        $this->enrollWithRecords($ana, $this->pastJoy, 'withdrawn', 'needs_support');
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->section($this->active, $omar, 'Hope');

        $this->postJson("/api/learners/{$ana->id}/enrollments", $this->payload())->assertUnauthorized();
        $this->getJson('/api/learners/re-enrollable?q=ana')->assertUnauthorized();
        $this->actingAs($omar)->postJson("/api/learners/{$ana->id}/enrollments", $this->payload(['section' => 'Hope']))->assertNotFound();
        $this->actingAs($omar)->getJson('/api/learners/re-enrollable?q=ana')->assertOk()->assertJsonCount(0);
        $this->assertSame(1, $ana->enrollments()->count());
    }

    public function test_search_finds_past_learners_by_name_or_lrn_with_their_blocked_reason(): void
    {
        $ana = $this->learner('Ana', lrn: '123456789012');
        $this->enrollWithRecords($ana, $this->pastJoy, 'withdrawn', 'needs_support');
        $bea = $this->learner('Bea');
        $this->enrollWithRecords($bea, $this->pastJoy, 'active', 'achieved');
        // Already in the active year, so not offered again.
        $cara = $this->learner('Cara');
        $this->enrollWithRecords($cara, $this->pastJoy, 'active', 'needs_support');
        $cara->enrollments()->create(['school_year_id' => $this->active->id, 'section_id' => Section::where(['school_year_id' => $this->active->id, 'name' => 'Joy'])->value('id'), 'enrolled_on' => '2026-06-01', 'status' => 'active']);

        $this->actingAs($this->tina)->getJson('/api/learners/re-enrollable?q=1234567')->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.learnerId', $ana->id)
            ->assertJsonPath('0.lastSchoolYear', '2025-2026')->assertJsonPath('0.section', 'Joy')
            ->assertJsonPath('0.lastStatus', 'withdrawn')->assertJsonPath('0.blockedReason', null);

        $rows = $this->actingAs($this->tina)->getJson('/api/learners/re-enrollable?q=cruz')->assertOk()->json();
        $this->assertSame([$ana->id, $bea->id], array_column($rows, 'learnerId'));
        $this->assertStringContainsString('Ready for Grade 1', $rows[1]['blockedReason']);

        $this->actingAs($this->tina)->getJson('/api/learners/re-enrollable?q=bea cruz')->assertOk()->assertJsonCount(1);
        $this->actingAs($this->tina)->getJson('/api/learners/re-enrollable?q=a')->assertStatus(422)->assertJsonValidationErrors('q');
    }
}

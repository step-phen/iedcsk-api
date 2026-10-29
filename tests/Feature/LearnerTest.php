<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\Province;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Teacher;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearnerTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $tina;

    private SchoolYear $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProvinceSeeder::class);
        $this->year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $this->tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        Section::create(['school_year_id' => $this->year->id, 'teacher_id' => $this->tina->id, 'grade_level' => 'Kindergarten', 'name' => 'Joy']);
    }

    private function payload(array $overrides = []): array
    {
        return [...[
            'firstName' => 'Ana',
            'lastName' => 'Cruz',
            'birthdate' => Carbon::now('Asia/Manila')->subYears(5)->toDateString(),
            'gender' => 'Female',
            'houseStreet' => '12 Mabini St',
            'barangay' => 'San Roque',
            'cityMunicipality' => 'Pasig',
            'provinceId' => Province::value('id'),
            'zipCode' => '1600',
            'gradeLevel' => 'Kindergarten',
            'section' => 'Joy',
            'schoolYear' => '2026-2027',
            'guardianCount' => 2,
            'guardianName_0' => 'Maria Cruz',
            'contactNumber_0' => '09171234567',
            'relationshipToLearner_0' => 'MOTHER',
            'guardianName_1' => 'Lito Reyes',
            'contactNumber_1' => '09181234567',
            'relationshipToLearner_1' => 'OTHER',
            'relationshipOther_1' => 'Godfather',
        ], ...$overrides];
    }

    public function test_provinces_list(): void
    {
        $this->actingAs($this->tina)->getJson('/api/provinces')->assertOk()->assertJsonCount(82);
    }

    public function test_creates_learner_with_address_guardians_enrollment_and_photo(): void
    {
        Storage::fake('public');

        $res = $this->actingAs($this->tina)->post('/api/learners', $this->payload([
            'photo' => UploadedFile::fake()->image('id.jpg'),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $learner = Learner::with(['address', 'guardians', 'enrollments.section'])->find($res->json('learnerId'));
        $enrollment = $learner->enrollments->first();
        $this->assertSame($this->tina->id, $enrollment->section->teacher_id);
        $this->assertSame($this->year->id, $enrollment->school_year_id);
        $this->assertSame('active', $enrollment->status);
        $this->assertSame('1600', $learner->address->zip_code);
        $this->assertSame(['MOTHER', 'Godfather'], $learner->guardians->pluck('relationship_to_learner')->all());
        $this->assertTrue(Storage::disk('public')->exists($learner->avatar_url));
    }

    public function test_stores_spaced_mobile_number_as_digits(): void
    {
        $res = $this->actingAs($this->tina)->postJson('/api/learners', $this->payload(['contactNumber_0' => '0917 123 4567']))->assertCreated();

        $this->assertSame('09171234567', Learner::find($res->json('learnerId'))->guardians->first()->contact_number);
    }

    public function test_downscales_uploaded_photo_to_512px_jpeg(): void
    {
        Storage::fake('public');

        $res = $this->actingAs($this->tina)->post('/api/learners', $this->payload([
            'photo' => UploadedFile::fake()->image('big.png', 2000, 1500),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $path = Learner::find($res->json('learnerId'))->avatar_url;
        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([512, 384, IMAGETYPE_JPEG], [$width, $height, $type]);
    }

    public function test_rejects_invalid_input_with_form_field_keys(): void
    {
        $this->actingAs($this->tina)->postJson('/api/learners', $this->payload([
            'lrn' => '123',
            'birthdate' => Carbon::now('Asia/Manila')->subYears(2)->toDateString(),
            'zipCode' => '12',
            'contactNumber_0' => '0917',
            'relationshipOther_1' => '',
            'schoolYear' => '2025-2026',
        ]))->assertStatus(422)->assertJsonValidationErrors([
            'lrn', 'birthdate', 'zipCode', 'contactNumber_0', 'relationshipOther_1', 'schoolYear',
        ]);

        $this->assertSame(0, Learner::count());
    }

    public function test_enrolls_into_whichever_year_is_active_and_rejects_others(): void
    {
        $next = SchoolYear::create(['name' => '2027-2028', 'start_date' => '2027-06-01', 'end_date' => '2028-03-31']);
        // The new year runs only Faith; Joy exists only in the old year.
        $this->actingAs($this->tina)->patchJson("/api/school-years/{$next->id}/activate", ['sections' => ['Faith']])->assertOk();

        // The old year's name, or a section that only exists in the old year, no longer passes.
        $this->actingAs($this->tina)->postJson('/api/learners', $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors(['schoolYear', 'section']);

        $res = $this->actingAs($this->tina)->postJson('/api/learners', $this->payload(['schoolYear' => '2027-2028', 'section' => 'Faith']))->assertCreated();

        $this->assertSame($next->id, Learner::find($res->json('learnerId'))->enrollments()->value('school_year_id'));
    }

    public function test_rejects_learner_without_an_active_school_year(): void
    {
        $this->year->update(['status' => 'completed']);

        $this->actingAs($this->tina)->postJson('/api/learners', $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors(['schoolYear', 'section']);
        $this->assertSame(0, Learner::count());
    }

    public function test_lists_only_own_learners_with_section(): void
    {
        $this->actingAs($this->tina)->postJson('/api/learners', $this->payload())->assertCreated();

        $this->actingAs($this->tina)->getJson('/api/learners')->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.firstName', 'Ana')
            ->assertJsonPath('0.section', 'Joy')
            ->assertJsonPath('0.gradeLevel', 'Kindergarten')
            ->assertJsonPath('0.photoUrl', null);

        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->actingAs($omar)->getJson('/api/learners')->assertOk()->assertJsonCount(0);
    }

    public function test_filters_by_section_scoped_to_owning_teacher(): void
    {
        $love = Section::create(['school_year_id' => $this->year->id, 'teacher_id' => $this->tina->id, 'grade_level' => 'Kindergarten', 'name' => 'Love']);
        $joyId = Section::where('name', 'Joy')->value('id');

        $this->actingAs($this->tina)->postJson('/api/learners', $this->payload())->assertCreated();
        $this->actingAs($this->tina)->postJson('/api/learners', $this->payload(['firstName' => 'Ben', 'section' => 'Love']))->assertCreated();

        $this->actingAs($this->tina)->getJson('/api/learners')->assertOk()->assertJsonCount(2);
        $this->actingAs($this->tina)->getJson("/api/learners?section={$joyId}")->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.firstName', 'Ana');
        $this->actingAs($this->tina)->getJson("/api/learners?section={$love->id}")->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.firstName', 'Ben');

        // Another teacher's section ID never leaks that section's roster.
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->actingAs($omar)->getJson("/api/learners?section={$joyId}")->assertOk()->assertJsonCount(0);
    }

    public function test_ready_for_grade_1_needs_every_quarter_rated_achieved_or_developing(): void
    {
        $this->actingAs($this->tina)->postJson('/api/learners', $this->payload())->assertCreated();
        $this->actingAs($this->tina)->postJson('/api/learners', $this->payload(['firstName' => 'Ben']))->assertCreated();
        $enrollment = fn (string $first) => DB::table('learner_school_years')->join('learners', 'learners.id', '=', 'learner_id')->where('first_name', $first)->value('learner_school_years.id');
        $domain = DB::table('developmental_domains')->insertGetId(['code' => 'math', 'name' => 'Math', 'display_order' => 1]);
        $item = fn (int $quarter) => DB::table('developmental_checklist_items')->insertGetId(['domain_id' => $domain, 'item' => "Skill Q{$quarter}", 'grade_level' => 'Kindergarten', 'quarter_number' => $quarter, 'display_order' => 1]);
        $rate = fn (string $first, int $item, int $quarter, string $rating) => DB::table('learner_developmental_checklists')->updateOrInsert(
            ['enrollment_id' => $enrollment($first), 'checklist_item_id' => $item, 'quarter_id' => DB::table('quarters')->where(['school_year_id' => $this->year->id, 'quarter_number' => $quarter])->value('id')],
            ['teacher_id' => $this->tina->id, 'rating' => $rating, 'assessed_at' => now()],
        );
        foreach ([1, 2, 3, 4] as $n) {
            DB::table('quarters')->insert(['school_year_id' => $this->year->id, 'quarter_number' => $n, 'name' => "Q{$n}", 'start_date' => sprintf('2026-%02d-01', 5 + $n), 'end_date' => sprintf('2026-%02d-28', 5 + $n), 'status' => 'upcoming']);
        }
        $ready = fn () => array_column($this->actingAs($this->tina)->getJson('/api/learners?ready=1')->assertOk()->json(), 'firstName');

        // Only Q1 to Q3 have skills so far, so even a fully rated learner is not ready yet.
        $items = array_map($item, [1, 2, 3]);
        foreach ($items as $i => $id) {
            $rate('Ana', $id, $i + 1, 'achieved');
            $rate('Ben', $id, $i + 1, 'developing');
        }
        $this->assertSame([], $ready());

        $q4 = $item(4);
        $this->assertSame([], $ready());
        $rate('Ana', $q4, 4, 'developing');
        $rate('Ben', $q4, 4, 'needs_support');
        $this->assertSame(['Ana'], $ready());
        $rate('Ben', $q4, 4, 'achieved');
        $this->assertSame(['Ana', 'Ben'], $ready());
    }

    public function test_shows_and_updates_own_learner_profile(): void
    {
        Storage::fake('public');
        $id = $this->actingAs($this->tina)->postJson('/api/learners', $this->payload(['lrn' => '123456789012']))->json('learnerId');

        $this->actingAs($this->tina)->getJson("/api/learners/{$id}")->assertOk()
            ->assertJsonPath('schoolYear', '2026-2027')
            ->assertJsonPath('address.zipCode', '1600')
            ->assertJsonPath('address.province', Province::value('province_name'))
            ->assertJsonPath('guardians.1.relationshipToLearner', 'Godfather')
            ->assertJsonPath('attendances', [])
            ->assertJsonPath('progress', []);

        // Progress lists the grade's checklist skills, each with this learner's rating for the skill's own quarter.
        $domain = DB::table('developmental_domains')->insertGetId(['code' => 'reading_language', 'name' => 'Reading and Language Development', 'display_order' => 1]);
        $item = fn (string $text, int $quarter, string $grade = 'Kindergarten') => DB::table('developmental_checklist_items')
            ->insertGetId(['domain_id' => $domain, 'item' => $text, 'grade_level' => $grade, 'quarter_number' => $quarter, 'display_order' => 1]);
        $same = $item('Tells same from different', 1);
        $item('Reads short words', 2);
        $item('Grade 1 skill', 1, 'Grade 1');
        $q1 = DB::table('quarters')->insertGetId(['school_year_id' => $this->year->id, 'quarter_number' => 1, 'name' => '1st Quarter', 'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'status' => 'active']);
        DB::table('learner_developmental_checklists')->insert([
            'enrollment_id' => DB::table('learner_school_years')->where('learner_id', $id)->value('id'),
            'checklist_item_id' => $same, 'quarter_id' => $q1, 'teacher_id' => $this->tina->id, 'rating' => 'developing', 'assessed_at' => now(),
        ]);
        $progress = $this->actingAs($this->tina)->getJson("/api/learners/{$id}")->json('progress');
        $this->assertSame([['Tells same from different', 1, 'developing'], ['Reads short words', 2, null]], array_map(fn ($p) => [$p['item'], $p['quarter'], $p['rating']], $progress));
        $this->assertSame('Reading and Language Development', $progress[0]['domainName']);

        $edit = [
            'lrn' => '123456789012', 'firstName' => 'Anna', 'lastName' => 'Cruz',
            'houseStreet' => '5 Rizal Ave', 'barangay' => 'Kapitolyo', 'cityMunicipality' => 'Pasig',
            'provinceId' => Province::value('id'), 'zipCode' => '1603',
            'guardianCount' => 2,
            'guardianName_0' => 'Maria Santos', 'contactNumber_0' => '09991234567',
            'relationshipToLearner_0' => 'OTHER', 'relationshipOther_0' => 'Aunt-in-law',
            'guardianName_1' => 'Lito Santos', 'contactNumber_1' => '09881234567',
            'relationshipToLearner_1' => 'FATHER', 'guardianEmail_1' => 'lito@example.com', 'occupation_1' => ' Driver ',
        ];
        // Keeping its own LRN is not a duplicate; a photo replaces the old one.
        $this->actingAs($this->tina)->post("/api/learners/{$id}", $edit + ['_method' => 'PUT', 'photo' => UploadedFile::fake()->image('new.png')], ['Accept' => 'application/json'])->assertOk();

        $learner = Learner::with(['address', 'guardians'])->find($id);
        // The name is not editable, so the submitted one is ignored.
        $this->assertSame('Ana', $learner->first_name);
        $this->assertSame('1603', $learner->address->zip_code);
        // Every guardian in the form is updated, not just the first.
        $this->assertSame(['Aunt-in-law', 'FATHER'], $learner->guardians->sortBy('id')->pluck('relationship_to_learner')->all());
        $this->assertSame(['Maria Santos', 'Lito Santos'], $learner->guardians->sortBy('id')->pluck('guardian_name')->all());
        $this->assertSame([null, 'lito@example.com'], $learner->guardians->sortBy('id')->pluck('guardian_email')->all());
        $this->assertSame([null, 'Driver'], $learner->guardians->sortBy('id')->pluck('occupation')->all());
        $this->assertTrue(Storage::disk('public')->exists($learner->avatar_url));

        $this->actingAs($this->tina)->putJson("/api/learners/{$id}", ['zipCode' => '12'] + $edit)->assertJsonValidationErrors('zipCode');

        // Another teacher's learner is indistinguishable from a missing one.
        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->actingAs($omar)->getJson("/api/learners/{$id}")->assertNotFound();
        $this->actingAs($omar)->putJson("/api/learners/{$id}", $edit)->assertNotFound();
    }

    public function test_excuse_slip_turns_an_absence_into_an_excused_day(): void
    {
        $id = $this->actingAs($this->tina)->postJson('/api/learners', $this->payload())->json('learnerId');
        $enrollment = DB::table('learner_school_years')->where('learner_id', $id)->value('id');
        foreach (['2026-10-05' => 'absent', '2026-10-06' => 'present'] as $date => $status) {
            DB::table('attendance')->insert(['enrollment_id' => $enrollment, 'attendance_date' => $date, 'status' => $status, 'recorded_by' => $this->tina->id, 'recorded_at' => now()]);
        }
        $slip = fn (array $body) => $this->actingAs($this->tina)->putJson("/api/learners/{$id}/excuse-slip", $body);

        // Only an absent day can be excused, and the reason is required.
        $slip(['date' => '2026-10-06', 'reason' => 'Fever'])->assertJsonValidationErrors('date');
        $slip(['date' => '2026-10-07', 'reason' => 'Fever'])->assertJsonValidationErrors('date');
        $slip(['date' => '2026-10-05', 'reason' => ''])->assertJsonValidationErrors('reason');

        $slip(['date' => '2026-10-05', 'reason' => 'Fever'])->assertOk();
        $this->assertSame(['excused', 'Fever'], [DB::table('attendance')->where('attendance_date', '2026-10-05')->value('status'), DB::table('attendance')->where('attendance_date', '2026-10-05')->value('excuse_reason')]);
        $this->assertSame('present', DB::table('attendance')->where('attendance_date', '2026-10-06')->value('status'));

        // The profile shows the excused day with its reason; excusing it twice is refused.
        $this->actingAs($this->tina)->getJson("/api/learners/{$id}")->assertJsonPath('attendances.0.status', 'excused')->assertJsonPath('attendances.0.reason', 'Fever');
        $slip(['date' => '2026-10-05', 'reason' => 'Fever'])->assertJsonValidationErrors('date');

        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->actingAs($omar)->putJson("/api/learners/{$id}/excuse-slip", ['date' => '2026-10-05', 'reason' => 'Fever'])->assertNotFound();
    }

    public function test_teacher_with_sections_cannot_be_deleted(): void
    {
        $this->expectException(QueryException::class);

        $this->tina->delete();
    }

    public function test_cannot_enroll_into_another_teachers_section(): void
    {
        $this->postJson('/api/learners', $this->payload())->assertUnauthorized();

        $omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);

        $this->actingAs($omar)->postJson('/api/learners', $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('section');
    }

    public function test_full_section_is_allowed_while_another_has_room_and_blocked_once_all_are_full(): void
    {
        $joy = Section::where('name', 'Joy')->first();
        $faith = Section::create(['school_year_id' => $this->year->id, 'teacher_id' => $this->tina->id, 'grade_level' => 'Kindergarten', 'name' => 'Faith']);
        $this->enroll($joy, Section::CAPACITY);
        // Only active enrollments take a seat.
        $this->enroll($faith, 1, 'withdrawn');

        $this->actingAs($this->tina)->getJson('/api/sections')->assertOk()
            ->assertJsonPath('0.learnerCount', Section::CAPACITY)->assertJsonPath('0.capacity', Section::CAPACITY)
            ->assertJsonPath('1.learnerCount', 0);

        // Faith still has room, so the teacher may put one more into the full Joy.
        $this->actingAs($this->tina)->postJson('/api/learners', $this->payload())->assertCreated();

        $this->enroll($faith, Section::CAPACITY);
        $this->actingAs($this->tina)->postJson('/api/learners', $this->payload(['section' => 'Faith']))
            ->assertStatus(422)->assertJsonValidationErrors('section');
    }

    private function enroll(Section $section, int $count, string $status = 'active'): void
    {
        for ($i = 0; $i < $count; $i++) {
            $learner = Learner::create(['first_name' => "L$i", 'last_name' => $section->name, 'birth_date' => '2021-01-01', 'gender' => 'Female']);
            $learner->enrollments()->create(['school_year_id' => $this->year->id, 'section_id' => $section->id, 'enrolled_on' => '2026-06-01', 'status' => $status]);
        }
    }
}

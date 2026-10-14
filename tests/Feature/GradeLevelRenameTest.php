<?php

namespace Tests\Feature;

use App\Models\Province;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Teacher;
use Database\Seeders\ActionSongSeeder;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// The Kinder 1 -> Kindergarten data migration. Fixtures here deliberately start from the old 'Kinder 1' value.
class GradeLevelRenameTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = ['sections', 'modules', 'developmental_checklist_items'];

    private function migration(): object
    {
        return require database_path('migrations/2026_10_15_000100_rename_kinder_1_to_kindergarten.php');
    }

    /** Puts every grade-level row back to the pre-rename value, as a database deployed before this change has it. */
    private function backToKinder1(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->update(['grade_level' => 'Kinder 1']);
        }
    }

    /** id => grade_level for each table. */
    private function gradeLevels(): array
    {
        return collect(self::TABLES)->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy('id')->pluck('grade_level', 'id')->all()])->all();
    }

    public function test_renames_in_place_keeping_ids_and_links(): void
    {
        $this->seed([ProvinceSeeder::class, CurriculumSeeder::class]);
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $joy = Section::create(['school_year_id' => $year->id, 'teacher_id' => $tina->id, 'grade_level' => 'Kindergarten', 'name' => 'Joy']);
        $this->actingAs($tina)->postJson('/api/learners', [
            'firstName' => 'Ana', 'lastName' => 'Cruz', 'birthdate' => Carbon::now('Asia/Manila')->subYears(5)->toDateString(), 'gender' => 'Female',
            'houseStreet' => '12 Mabini St', 'barangay' => 'San Roque', 'cityMunicipality' => 'Pasig', 'provinceId' => Province::value('id'), 'zipCode' => '1600',
            'gradeLevel' => 'Kindergarten', 'section' => 'Joy', 'schoolYear' => '2026-2027',
            'guardianCount' => 1, 'guardianName_0' => 'Maria Cruz', 'contactNumber_0' => '09171234567', 'relationshipToLearner_0' => 'MOTHER',
        ])->assertCreated();
        $enrollment = DB::table('learner_school_years')->first();
        $item = DB::table('developmental_checklist_items')->insertGetId(['domain_id' => DB::table('developmental_domains')->value('id'), 'item' => 'Counts to 10', 'grade_level' => 'Kindergarten', 'quarter_number' => 1, 'display_order' => 1]);
        $quarter = DB::table('quarters')->insertGetId(['school_year_id' => $year->id, 'quarter_number' => 1, 'name' => 'Q1', 'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'status' => 'active']);
        $rating = DB::table('learner_developmental_checklists')->insertGetId(['enrollment_id' => $enrollment->id, 'checklist_item_id' => $item, 'quarter_id' => $quarter, 'teacher_id' => $tina->id, 'rating' => 'achieved', 'assessed_at' => now()]);
        $this->backToKinder1();
        $before = $this->gradeLevels();

        $this->migration()->up();

        $after = $this->gradeLevels();
        foreach (self::TABLES as $table) {
            $this->assertSame(array_keys($before[$table]), array_keys($after[$table]), "{$table} ids changed");
            $this->assertSame(['Kindergarten'], array_values(array_unique($after[$table])), "{$table} not fully renamed");
        }
        $this->assertSame($joy->id, DB::table('learner_school_years')->where('id', $enrollment->id)->value('section_id'));
        $this->assertSame($item, DB::table('learner_developmental_checklists')->where('id', $rating)->value('checklist_item_id'));
        $this->actingAs($tina)->getJson('/api/learners')->assertOk()->assertJsonPath('0.gradeLevel', 'Kindergarten')->assertJsonPath('0.section', 'Joy');

        // Running it again changes nothing.
        $this->migration()->up();
        $this->assertSame($after, $this->gradeLevels());
    }

    public function test_a_clash_on_any_table_leaves_every_table_untouched(): void
    {
        $this->seed(CurriculumSeeder::class);
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        DB::table('sections')->insert(['school_year_id' => $year->id, 'teacher_id' => $tina->id, 'name' => 'Joy', 'grade_level' => 'Kindergarten']);
        DB::table('developmental_checklist_items')->insert(['domain_id' => DB::table('developmental_domains')->value('id'), 'item' => 'Hops', 'grade_level' => 'Kindergarten', 'quarter_number' => 1, 'display_order' => 1]);
        $this->backToKinder1();
        // Sections are renamed first, then this module collides with the old 'math' one on (grade_level, code).
        DB::table('modules')->insert(['code' => 'math', 'name' => 'Math', 'grade_level' => 'Kindergarten']);
        $before = $this->gradeLevels();

        $this->assertThrows(fn () => $this->migration()->up(), QueryException::class);

        $this->assertSame($before, $this->gradeLevels());
        $this->assertSame('Kinder 1', DB::table('sections')->value('grade_level'));
    }

    public function test_rerunning_seeders_after_the_rename_adds_no_duplicates(): void
    {
        $this->seed(CurriculumSeeder::class);
        $lesson = DB::table('lessons')->insertGetId([
            'module_domain_id' => DB::table('module_domains')
                ->join('modules', 'modules.id', '=', 'module_domains.module_id')
                ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
                ->where(['modules.code' => 'mapeh', 'developmental_domains.code' => 'gross_motor'])
                ->value('module_domains.id'),
            'title' => 'Moving My Body', 'quarter_number' => 1, 'week_number' => 1, 'day_of_week' => 4, 'sequence_order' => 1,
        ]);
        $this->seed(ActionSongSeeder::class);
        $this->backToKinder1();
        $this->migration()->up();
        $modules = DB::table('modules')->orderBy('id')->pluck('grade_level', 'id')->all();
        $items = DB::table('developmental_checklist_items')->orderBy('id')->pluck('grade_level', 'id')->all();

        $this->seed([CurriculumSeeder::class, ActionSongSeeder::class]);
        $this->seed([CurriculumSeeder::class, ActionSongSeeder::class]);

        $this->assertSame($modules, DB::table('modules')->orderBy('id')->pluck('grade_level', 'id')->all());
        $this->assertSame($items, DB::table('developmental_checklist_items')->orderBy('id')->pluck('grade_level', 'id')->all());
        $this->assertSame(['Kindergarten'], array_values(array_unique($modules)));
        $this->assertSame(1, DB::table('activities')->where('lesson_id', $lesson)->count());
    }
}

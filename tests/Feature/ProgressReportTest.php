<?php

namespace Tests\Feature;

use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Teacher;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProgressReportTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $tina;

    private Teacher $omar;

    private int $section;

    private int $gabriel;

    private array $quarters;

    private int $order = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CurriculumSeeder::class);
        $year = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $this->tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']);
        $this->omar = Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'x']);
        $this->section = $this->setUpSections($this->tina, ['Joy'])['Joy'];
        foreach ([1 => 'active', 2 => 'upcoming'] as $n => $status) {
            $this->quarters[$n] = DB::table('quarters')->insertGetId(['school_year_id' => $year->id, 'quarter_number' => $n, 'name' => "Q{$n}", 'start_date' => '2026-0'.(5 + $n).'-01', 'end_date' => '2026-0'.(5 + $n).'-28', 'status' => $status]);
        }
        $this->gabriel = DB::table('learner_school_years')->insertGetId([
            'learner_id' => DB::table('learners')->insertGetId(['first_name' => 'Gabriel', 'last_name' => 'Reyes', 'birth_date' => '2021-01-01', 'gender' => 'Male']),
            'school_year_id' => $year->id, 'section_id' => $this->section, 'enrolled_on' => '2026-06-01', 'status' => 'active',
        ]);
    }

    // One game in the module's domain with the checklist skill it practises; returns [activityId, itemId].
    private function skill(string $module, string $domain, string $item, string $format = 'digital', int $quarter = 1, bool $linked = true): array
    {
        $this->order++;
        $moduleDomain = DB::table('module_domains')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->where(['modules.code' => $module, 'developmental_domains.code' => $domain])
            ->first(['module_domains.id', 'module_domains.domain_id']);
        $activity = DB::table('activities')->insertGetId(['lesson_id' => DB::table('lessons')->insertGetId([
            'module_domain_id' => $moduleDomain->id, 'title' => 'Lesson', 'quarter_number' => $quarter, 'week_number' => 1, 'sequence_order' => $this->order,
        ]), 'activity_code' => "T-{$this->order}", 'name' => "Game {$this->order}", 'instructions' => '-', 'activity_format' => $format, 'sequence_order' => 1]);
        $itemId = DB::table('developmental_checklist_items')->insertGetId([
            'domain_id' => $moduleDomain->domain_id, 'item' => $item, 'grade_level' => 'Kindergarten', 'quarter_number' => $quarter,
            'activity_id' => $linked ? $activity : null, 'display_order' => $this->order,
        ]);

        return [$activity, $itemId];
    }

    private function played(int $activity, array $attempt, int $playedQuarter = 1, int $number = 1): void
    {
        $assignment = DB::table('learner_activity_assignments')->insertGetId([
            'enrollment_id' => $this->gabriel, 'activity_id' => $activity, 'quarter_id' => $this->quarters[$playedQuarter], 'teacher_id' => $this->tina->id,
            'assigned_at' => now(), 'status' => 'completed', 'completed_at' => now(),
        ]);
        DB::table('learner_activity_attempts')->insert($attempt + ['assignment_id' => $assignment, 'attempt_number' => $number, 'started_at' => now(), 'status' => 'evaluated', 'evaluated_at' => now()]);
    }

    private function generate(int $quarter = 1, ?Teacher $as = null)
    {
        return $this->actingAs($as ?? $this->tina)->postJson("/api/sections/{$this->section}/progress-reports", ['enrollmentId' => $this->gabriel, 'quarter' => $quarter]);
    }

    private function row(array $report, string $subject, ?string $subheading = null): array
    {
        return collect($report['rows'])->first(fn ($r) => $r['subject'] === $subject && $r['subheading'] === $subheading);
    }

    public function test_generates_a_draft_from_recorded_evidence_only(): void
    {
        [$shapes] = $this->skill('math', 'math_readiness', 'Matches basic shapes');
        [$numbers] = $this->skill('math', 'math_readiness', 'Matches numerals 1 to 10 with groups of that many objects');
        [$count, $countItem] = $this->skill('math', 'math_readiness', 'Counts objects from 1 to 10');
        [$sort] = $this->skill('math', 'math_readiness', 'Sorts objects by size', 'practical');
        $this->skill('math', 'math_readiness', 'Continues an AB pattern');
        [$q2game] = $this->skill('math', 'math_readiness', 'Counts to 20', 'digital', 2);
        [$draw] = $this->skill('mapeh', 'arts_crafts', 'Colors inside the lines', 'practical');
        // Reading and Language share a domain, so an unlinked skill there has no row to go to.
        $this->skill('reading', 'reading_language', 'Names the letters A to E', 'digital', 1, false);

        $this->played($shapes, ['score' => 5, 'max_score' => 5]);
        // Played during a Q2 class, but it is a Q1 skill.
        $this->played($numbers, ['score' => 2, 'max_score' => 5], 2);
        $this->played($count, ['score' => 4, 'max_score' => 5]);
        DB::table('learner_developmental_checklists')->insert(['enrollment_id' => $this->gabriel, 'checklist_item_id' => $countItem, 'quarter_id' => $this->quarters[1], 'teacher_id' => $this->tina->id, 'rating' => 'developing', 'assessed_at' => now()]);
        $this->played($sort, ['status' => 'submitted', 'evaluated_at' => null, 'submitted_at' => now()]);
        $this->played($q2game, ['score' => 0, 'max_score' => 5], 2);
        $this->played($draw, ['practical_rating' => 5, 'evaluated_by' => $this->tina->id, 'remarks' => 'Neat work.']);

        $report = $this->generate()->assertCreated()->json();
        $this->assertSame('draft', $report['status']);
        $this->assertSame(
            ['LANGUAGE', 'READING', 'WRITING READINESS', 'MATH READINESS', 'MAPEH', 'MAPEH', 'MAPEH'],
            array_column($report['rows'], 'subject'),
        );
        $this->assertSame(['GROSS MOTOR DEVELOPMENT', 'ARTS AND CRAFTS', 'HEALTH, WORK HABITS AND ATTITUDE'], array_slice(array_column($report['rows'], 'subheading'), 4));
        $this->assertSame([true, true, true, true, true, false, false], array_column($report['rows'], 'remarksRow'));

        $math = $this->row($report, 'MATH READINESS');
        $this->assertSame(
            // "A" (Accomplished, stored as developing) reads as a strength.
            "Gabriel counts objects from 1 to 10, as observed by the teacher. He matches basic shapes in the completed digital activity.\n\n"
            .'He needs more practice to match numerals 1 to 10 with groups of that many objects in the completed digital activity.',
            $math['developmentObserved'],
        );
        $this->assertSame($math['developmentObserved'], $math['generatedObserved']);
        // Comments/Remarks are the teacher's to write: every box starts blank.
        $this->assertSame([null], array_unique(array_column($report['rows'], 'commentsRemarks')));
        // The Q1 game played during a Q2 class still counts for Q1, and says where it was played; Q2's own skill is not here.
        $this->assertSame(2, collect($math['evidence'])->firstWhere('activityId', $numbers)['result']['playedQuarter']);
        $this->assertSame(['assessed', 'assessed', 'assessed', 'awaiting_rating', 'not_assessed'], array_column($math['evidence'], 'status'));

        $this->assertSame('Gabriel colors inside the lines during the hands-on activity.', $this->row($report, 'MAPEH', 'ARTS AND CRAFTS')['developmentObserved']);
        $this->assertSame('Neat work.', $this->row($report, 'MAPEH', 'ARTS AND CRAFTS')['evidence'][0]['result']['remarks']);
        $this->assertSame('Not yet assessed.', $this->row($report, 'READING')['developmentObserved']);
        $this->assertSame(['Names the letters A to E'], array_column($report['unplaced'], 'item'));

        // A second click returns the same report; the roster shows it.
        $this->assertSame($report['reportId'], $this->generate()->assertOk()->json('reportId'));
        $this->assertSame(1, DB::table('quarterly_progress_reports')->count());
        $roster = $this->actingAs($this->tina)->getJson("/api/sections/{$this->section}/progress-reports?quarter=1")->assertOk()->json();
        $this->assertSame([[$report['reportId'], 'draft']], array_map(fn ($l) => [$l['reportId'], $l['reportStatus']], $roster['learners']));
        $this->assertSame([true, true, false, false], array_column($roster['quarters'], 'available'));

        $q2 = $this->generate(2)->assertCreated()->json();
        $this->assertSame('Gabriel needs more practice to count to 20 in the completed digital activity.', $this->row($q2, 'MATH READINESS')['developmentObserved']);
        $this->generate(3)->assertJsonValidationErrors(['quarter' => 'This quarter has not been set up for the school year yet.']);
    }

    public function test_other_teachers_get_nothing_and_a_handover_moves_access(): void
    {
        $id = $this->generate()->json('reportId');
        $this->generate(1, $this->omar)->assertNotFound();
        $this->actingAs($this->omar)->getJson("/api/sections/{$this->section}/progress-reports")->assertNotFound();
        $this->actingAs($this->omar)->getJson("/api/progress-reports/{$id}")->assertNotFound();
        $this->actingAs($this->omar)->putJson("/api/progress-reports/{$id}", [])->assertNotFound();
        foreach (['regenerate', 'finalize', 'reopen'] as $action) {
            $this->actingAs($this->omar)->postJson("/api/progress-reports/{$id}/{$action}", [])->assertNotFound();
        }
        $this->actingAs($this->omar)->getJson("/api/progress-reports/{$id}/versions/1")->assertNotFound();

        // An enrollment from someone else's section cannot be put in Tina's.
        $omars = Section::create(['school_year_id' => SchoolYear::active()->id, 'teacher_id' => $this->omar->id, 'grade_level' => 'Kindergarten', 'name' => 'Hope'])->id;
        DB::table('learner_school_years')->where('id', $this->gabriel)->update(['section_id' => $omars]);
        $this->generate(2)->assertJsonValidationErrors('enrollmentId');
        DB::table('learner_school_years')->where('id', $this->gabriel)->update(['section_id' => $this->section]);

        // Accepting a handover moves the section, and its reports go with it.
        DB::table('sections')->where('id', $this->section)->update(['teacher_id' => $this->omar->id]);
        $this->actingAs($this->omar)->getJson("/api/progress-reports/{$id}")->assertOk()->assertJsonPath('header.teacherName', 'Omar');
        $this->actingAs($this->tina)->getJson("/api/progress-reports/{$id}")->assertNotFound();

        DB::table('teacher_handovers')->insert(['from_teacher_id' => $this->tina->id, 'to_teacher_id' => $this->omar->id, 'status' => 'pending']);
        $this->actingAs($this->omar)->getJson("/api/progress-reports/{$id}")->assertForbidden()->assertJsonPath('code', 'handover_pending');
    }

    public function test_edits_survive_regeneration_and_finalized_versions_never_change(): void
    {
        [$shapes] = $this->skill('math', 'math_readiness', 'Matches basic shapes');
        [$hop] = $this->skill('mapeh', 'gross_motor', 'Hops on one foot', 'practical');
        $report = $this->generate()->json();
        $id = $report['reportId'];
        // Sends every row of the latest copy, with some changed.
        $save = function (array $changes, ?string $updatedAt = null) use (&$report, $id) {
            return $this->actingAs($this->tina)->putJson("/api/progress-reports/{$id}", [
                'updatedAt' => $updatedAt ?? $report['updatedAt'],
                'rows' => collect($report['rows'])->map(fn ($r) => ($changes[$r['subheading'] ?? $r['subject']] ?? [])
                    + ['moduleDomainId' => $r['moduleDomainId'], 'developmentObserved' => $r['developmentObserved'], 'commentsRemarks' => $r['commentsRemarks']])->all(),
            ]);
        };

        $save(['MATH READINESS' => ['developmentObserved' => str_repeat('a', 2001)]])->assertJsonValidationErrors('rows.3.developmentObserved');
        $save(['ARTS AND CRAFTS' => ['commentsRemarks' => 'Here?']])->assertJsonValidationErrors('rows.5.commentsRemarks');
        $report = $save([
            'MATH READINESS' => ['developmentObserved' => 'Gabriel enjoys shapes.', 'commentsRemarks' => 'Practise counting at home.'],
            'GROSS MOTOR DEVELOPMENT' => ['commentsRemarks' => 'Keep moving.'],
        ])->assertOk()->json();
        $save([], '2000-01-01 00:00:00+00')->assertStatus(409);

        // New evidence: the untouched row picks it up, the edited row keeps the teacher's words.
        $this->played($shapes, ['score' => 5, 'max_score' => 5]);
        $this->played($hop, ['practical_rating' => 3, 'evaluated_by' => $this->tina->id]);
        $report = $this->actingAs($this->tina)->postJson("/api/progress-reports/{$id}/regenerate")->assertOk()->json();
        $math = $this->row($report, 'MATH READINESS');
        $this->assertSame('Gabriel enjoys shapes.', $math['developmentObserved']);
        $this->assertSame('Gabriel matches basic shapes in the completed digital activity.', $math['generatedObserved']);
        $this->assertSame('Practise counting at home.', $math['commentsRemarks']);
        $motor = $this->row($report, 'MAPEH', 'GROSS MOTOR DEVELOPMENT');
        $this->assertSame('Gabriel is still learning to hop on one foot during the hands-on activity.', $motor['developmentObserved']);
        $this->assertSame('Keep moving.', $motor['commentsRemarks']);
        $this->assertArrayNotHasKey('generatedRemarks', $motor);
        $this->assertNull($this->row($report, 'LANGUAGE')['commentsRemarks']);

        $finalize = fn (array $r) => $this->actingAs($this->tina)->postJson("/api/progress-reports/{$id}/finalize", ['updatedAt' => $r['updatedAt']]);
        $blank = $save(['READING' => ['developmentObserved' => ' ']], $report['updatedAt'])->assertOk()->json();
        $finalize($blank)->assertJsonValidationErrors(['rows' => 'Fill in Development Observed for READING before finalizing.']);
        $report = $save(['READING' => ['developmentObserved' => 'Not yet assessed.']], $blank['updatedAt'])->json();
        $report = $finalize($report)->assertOk()->json();
        $this->assertSame('finalized', $report['status']);
        $this->assertSame([[1, 'Tina', null]], array_map(fn ($v) => [$v['version'], $v['finalizedBy'], $v['correctionReason']], $report['versions']));
        $v1 = $this->actingAs($this->tina)->get("/api/progress-reports/{$id}/versions/1")->assertOk()->getContent();
        $this->assertSame('Gabriel enjoys shapes.', $this->row(json_decode($v1, true), 'MATH READINESS')['developmentObserved']);

        // Finalized means read-only until reopened with a reason.
        $save([], $report['updatedAt'])->assertStatus(409);
        $this->actingAs($this->tina)->postJson("/api/progress-reports/{$id}/regenerate")->assertStatus(409);
        $finalize($report)->assertStatus(409);
        $this->actingAs($this->tina)->postJson("/api/progress-reports/{$id}/reopen", [])->assertJsonValidationErrors('reason');

        // Later changes to names, results and ownership leave version 1 exactly as it was.
        DB::table('learners')->update(['first_name' => 'Gabby']);
        $this->played($shapes, ['score' => 1, 'max_score' => 5], 1, 2);
        DB::table('sections')->where('id', $this->section)->update(['teacher_id' => $this->omar->id]);
        $this->assertSame($v1, $this->actingAs($this->omar)->get("/api/progress-reports/{$id}/versions/1")->getContent());

        $report = $this->actingAs($this->omar)->postJson("/api/progress-reports/{$id}/reopen", ['reason' => 'Name spelling.'])->assertOk()->json();
        $this->assertSame(['draft', 'Name spelling.'], [$report['status'], $report['correctionReason']]);
        $this->actingAs($this->omar)->postJson("/api/progress-reports/{$id}/reopen", ['reason' => 'Again.'])->assertStatus(409);
        $report = $this->actingAs($this->omar)->postJson("/api/progress-reports/{$id}/finalize", ['updatedAt' => $report['updatedAt']])->assertOk()->json();
        $this->assertSame([[1, 'Tina', null], [2, 'Omar', 'Name spelling.']], array_map(fn ($v) => [$v['version'], $v['finalizedBy'], $v['correctionReason']], $report['versions']));
        $this->assertSame('Gabby', $this->actingAs($this->omar)->getJson("/api/progress-reports/{$id}/versions/2")->json('header.firstName'));
        $this->assertSame($v1, $this->actingAs($this->omar)->get("/api/progress-reports/{$id}/versions/1")->getContent());
        $this->actingAs($this->omar)->getJson("/api/progress-reports/{$id}/versions/3")->assertNotFound();
    }
}

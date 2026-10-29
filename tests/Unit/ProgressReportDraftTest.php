<?php

namespace Tests\Unit;

use App\Support\ProgressReportDraft;
use PHPUnit\Framework\TestCase;

class ProgressReportDraftTest extends TestCase
{
    private function skill(string $item, ?string $level, ?string $source): array
    {
        return ['item' => $item, 'level' => $level, 'source' => $source];
    }

    public function test_base_form_of_a_checklist_item(): void
    {
        $this->assertSame('match numerals 1 to 10', ProgressReportDraft::baseForm('Matches numerals 1 to 10'));
        $this->assertSame('identify the odd one out', ProgressReportDraft::baseForm('Identifies the odd one out.'));
        $this->assertSame('trace the letter A', ProgressReportDraft::baseForm('Traces the letter A'));
        $this->assertSame('recognize basic shapes', ProgressReportDraft::baseForm('Recognizes basic shapes'));
        $this->assertSame('buzz like a bee', ProgressReportDraft::baseForm('Buzzes like a bee'));
        $this->assertSame('tie shoelaces', ProgressReportDraft::baseForm('Ties shoelaces'));
        $this->assertSame('go up the stairs', ProgressReportDraft::baseForm('Goes up the stairs'));
        $this->assertSame('be able to hop', ProgressReportDraft::baseForm('Is able to hop'));
        $this->assertSame('role-play a shopkeeper', ProgressReportDraft::baseForm('Role-plays a shopkeeper'));
        $this->assertNull(ProgressReportDraft::baseForm('Shape recognition'));
        $this->assertNull(ProgressReportDraft::baseForm('Class discussion'));
        $this->assertNull(ProgressReportDraft::baseForm('1 to 10'));
    }

    public function test_digital_scores_use_the_classroom_record_bands(): void
    {
        $this->assertSame('strength', ProgressReportDraft::scoreLevel(4, 5));
        $this->assertSame('developing', ProgressReportDraft::scoreLevel(3, 5));
        $this->assertSame('needs', ProgressReportDraft::scoreLevel(2, 5));
    }

    public function test_the_teachers_checklist_rating_outranks_a_score(): void
    {
        $item = (object) ['id' => 1, 'item' => 'Counts objects from 1 to 10', 'activity_id' => 9, 'activity_name' => 'Count', 'activity_format' => 'digital'];
        $result = (object) ['attempt_id' => 3, 'score' => '4', 'max_score' => '5', 'practical_rating' => null, 'remarks' => null, 'played_quarter' => 1, 'finished_at' => 'x'];

        $skill = ProgressReportDraft::evidence($item, (object) ['id' => 2, 'rating' => 'needs_support', 'remarks' => null, 'assessed_at' => 'x'], $result);
        $this->assertSame(['needs', 'checklist', 'assessed'], [$skill['level'], $skill['source'], $skill['status']]);
        $this->assertSame(4.0, $skill['result']['score']);

        // "A" (Accomplished, stored as developing) is accomplished, so it reads as a strength like "HA".
        $skill = ProgressReportDraft::evidence($item, (object) ['id' => 2, 'rating' => 'developing', 'remarks' => null, 'assessed_at' => 'x'], $result);
        $this->assertSame(['strength', 'checklist'], [$skill['level'], $skill['source']]);

        // "/" (Not yet introduced) says nothing, so the score decides.
        $skill = ProgressReportDraft::evidence($item, (object) ['id' => 2, 'rating' => 'not_yet_observed', 'remarks' => null, 'assessed_at' => 'x'], $result);
        $this->assertSame(['strength', 'digital'], [$skill['level'], $skill['source']]);

        // A practical done but not rated yet is waiting on the teacher, not a weakness.
        $unrated = (object) ['attempt_id' => 4, 'score' => null, 'max_score' => null, 'practical_rating' => null, 'remarks' => null, 'played_quarter' => 1, 'finished_at' => 'x'];
        $this->assertSame([null, 'awaiting_rating'], [ProgressReportDraft::evidence($item, null, $unrated)['level'], ProgressReportDraft::evidence($item, null, $unrated)['status']]);
        $this->assertSame('not_assessed', ProgressReportDraft::evidence($item, null, null)['status']);
    }

    public function test_observed_text_matches_the_planned_example(): void
    {
        $skills = [
            $this->skill('Matches basic shapes', 'strength', 'digital'),
            $this->skill('Matches numerals 1 to 10 with groups of that many objects', 'needs', 'digital'),
            $this->skill('Counts objects from 1 to 10', 'developing', 'checklist'),
            $this->skill('Sorts objects by size', null, null),
        ];

        $this->assertSame(
            "Gabriel matches basic shapes in the completed digital activity.\n\n"
            .'He is still learning to count objects from 1 to 10, as observed by the teacher. He needs more practice to match numerals 1 to 10 with groups of that many objects in the completed digital activity.',
            ProgressReportDraft::observed('Gabriel', 'Male', $skills),
        );
    }

    public function test_letter_skills_that_differ_only_in_letters_read_as_one_sentence(): void
    {
        $this->assertSame(
            'Divine is still learning to recognize uppercase and lowercase Xx, Yy, Vv, and Ww after hearing the letter names in the completed digital activities. '
            .'She needs more practice with these letters.',
            ProgressReportDraft::observed('divine', 'Female', [
                $this->skill('Recognizes uppercase and lowercase Xx and Yy after hearing the letter names', 'developing', 'digital') + ['activityId' => 7],
                $this->skill('Recognizes uppercase and lowercase Vv and Ww after hearing the letter names', 'needs', 'digital') + ['activityId' => 8],
            ]),
        );

        // Two skills practised in one game still say "activity".
        $this->assertSame(
            'Ana recognizes uppercase and lowercase Bb and Cc in the completed digital activity.',
            ProgressReportDraft::observed('Ana', null, [
                $this->skill('Recognizes uppercase and lowercase Bb', 'strength', 'digital') + ['activityId' => 7],
                $this->skill('Recognizes uppercase and lowercase Cc', 'strength', 'digital') + ['activityId' => 7],
            ]),
        );

        // Same level merges without the extra line; a skill without letters keeps its own wording.
        $this->assertSame(
            'Ana needs more practice to recognize uppercase and lowercase Bb, Cc, and Dd after hearing the letter names and count to 20 in the completed digital activities.',
            ProgressReportDraft::observed('Ana', null, [
                $this->skill('Recognizes uppercase and lowercase Bb and Cc after hearing the letter names', 'needs', 'digital'),
                $this->skill('Counts to 20', 'needs', 'digital'),
                $this->skill('Recognizes uppercase and lowercase Dd after hearing the letter names', 'needs', 'digital'),
            ]),
        );
    }

    public function test_wording_without_gender_without_evidence_and_with_odd_items(): void
    {
        $this->assertSame(ProgressReportDraft::NOT_ASSESSED, ProgressReportDraft::observed('Ana', null, []));
        $this->assertSame(ProgressReportDraft::NOT_ASSESSED, ProgressReportDraft::observed('Ana', null, [$this->skill('Hops', null, null)]));

        $text = ProgressReportDraft::observed('Ana', null, [
            $this->skill('Hops on one foot', 'strength', 'practical'),
            $this->skill('Skips', 'strength', 'practical'),
            $this->skill('Shape recognition', 'needs', 'checklist'),
            $this->skill('Throws a ball', 'needs', 'practical'),
        ]);
        $this->assertSame(
            "Ana hops on one foot and skips during the hands-on activities.\n\n"
            .'Ana needs more practice with the skill "Shape recognition", as observed by the teacher. Ana needs help to throw a ball during the hands-on activity.',
            $text,
        );
        $this->assertDoesNotMatchRegularExpression('/master|improv|independent|with guidance/i', $text);
    }
}

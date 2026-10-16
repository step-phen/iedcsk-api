<?php

namespace App\Support;

use App\Http\Controllers\ClassroomRecordController;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Rule-based first draft of a learner's quarterly progress report. It only states what was recorded for the
// quarter's checklist skills (the teacher's checklist rating, else a practical rating, else a digital score),
// never guesses a skill from an activity title, and never claims mastery or improvement.
class ProgressReportDraft
{
    // Report rows in print order; modules outside this list are not on the report.
    public const SUBJECTS = ['language' => 'LANGUAGE', 'reading' => 'READING', 'writing' => 'WRITING READINESS', 'math' => 'MATH READINESS', 'mapeh' => 'MAPEH'];

    public const SUBHEADINGS = ['gross_motor' => 'GROSS MOTOR DEVELOPMENT', 'arts_crafts' => 'ARTS AND CRAFTS', 'health_habits' => 'HEALTH, WORK HABITS AND ATTITUDE'];

    public const NOT_ASSESSED = 'Not yet assessed.';

    // ponytail: the Classroom Record legend's bands (score-band.ts) until the school sets its own cut-offs.
    public const DIGITAL_STRENGTH = 80;

    public const DIGITAL_DEVELOPING = 60;

    private const CHECKLIST_LEVELS = ['achieved' => 'strength', 'developing' => 'developing', 'needs_support' => 'needs'];

    private const PRACTICAL_LEVELS = ['very_good' => 'strength', 'good' => 'developing', 'needs_help' => 'needs'];

    // The report rows a section's active modules give, in print order.
    public static function rows(int $sectionId): Collection
    {
        return self::labelled(DB::table('module_domains')->whereIn('module_domains.module_id', DB::table('section_modules')
            ->where(['section_id' => $sectionId, 'is_active' => true])->select('module_id')));
    }

    // The rows a saved report already has, in print order.
    public static function rowsOf(int $reportId): Collection
    {
        return self::labelled(DB::table('module_domains')->whereIn('module_domains.id', DB::table('quarterly_progress_report_details')
            ->where('report_id', $reportId)->select('module_domain_id')));
    }

    private static function labelled(Builder $query): Collection
    {
        $order = array_keys(self::SUBJECTS);
        $rows = $query
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->join('developmental_domains', 'developmental_domains.id', '=', 'module_domains.domain_id')
            ->whereIn('modules.code', $order)
            ->orderBy('module_domains.id')
            ->get(['module_domains.id as module_domain_id', 'module_domains.domain_id', 'modules.code as module_code', 'developmental_domains.code as domain_code', 'developmental_domains.name as domain_name'])
            ->sortBy(fn ($r) => array_search($r->module_code, $order))
            ->values();

        $seen = [];

        return $rows->map(function ($r) use (&$seen) {
            $r->subject = self::SUBJECTS[$r->module_code];
            // Only MAPEH splits into subheadings; its remarks are one cell, kept on its first row.
            $r->subheading = $r->module_code === 'mapeh' ? (self::SUBHEADINGS[$r->domain_code] ?? mb_strtoupper($r->domain_name)) : null;
            $r->remarks_row = ! isset($seen[$r->module_code]);
            $seen[$r->module_code] = true;

            return $r;
        });
    }

    /**
     * Draft text and evidence per row for one report: $report carries section_id, grade_level, enrollment_id,
     * quarter_id, quarter_number, first_name and gender. Skills no row can claim come back as 'unplaced'.
     *
     * @return array{rows: array<int, array{observed: string, evidence: array}>, unplaced: array}
     */
    public static function build(object $report): array
    {
        $rows = self::rows($report->section_id);
        $items = DB::table('developmental_checklist_items')
            ->leftJoin('activities', 'activities.id', '=', 'developmental_checklist_items.activity_id')
            ->leftJoin('lessons', 'lessons.id', '=', 'activities.lesson_id')
            ->where([
                'developmental_checklist_items.grade_level' => $report->grade_level,
                'developmental_checklist_items.is_active' => true,
                'developmental_checklist_items.quarter_number' => $report->quarter_number,
            ])
            ->orderBy('developmental_checklist_items.display_order')->orderBy('developmental_checklist_items.id')
            ->get(['developmental_checklist_items.id', 'developmental_checklist_items.item', 'developmental_checklist_items.domain_id', 'developmental_checklist_items.activity_id', 'activities.name as activity_name', 'activities.activity_format', 'lessons.module_domain_id']);

        $ratings = DB::table('learner_developmental_checklists')
            ->where(['enrollment_id' => $report->enrollment_id, 'quarter_id' => $report->quarter_id])
            ->whereIn('checklist_item_id', $items->pluck('id'))
            ->get(['id', 'checklist_item_id', 'rating', 'remarks', 'assessed_at'])
            ->keyBy('checklist_item_id');
        $results = ClassroomRecordController::latestResults(collect([$report->enrollment_id]), $items->pluck('activity_id')->filter()->unique()->values())
            ->keyBy('activity_id');

        $byDomain = $rows->groupBy('domain_id');
        $skills = [];
        $unplaced = [];
        foreach ($items as $item) {
            $row = self::place($rows, $byDomain, $item);
            $skill = self::evidence($item, $ratings->get($item->id), $item->activity_id !== null ? $results->get($item->activity_id) : null);
            if ($row) {
                $skills[$row->module_domain_id][] = $skill;
            } else {
                $unplaced[] = $skill;
            }
        }

        $out = [];
        foreach ($rows as $row) {
            $mine = $skills[$row->module_domain_id] ?? [];
            $out[$row->module_domain_id] = ['observed' => self::observed($report->first_name, $report->gender, $mine), 'evidence' => $mine];
        }

        return ['rows' => $out, 'unplaced' => $unplaced];
    }

    // A linked skill goes to its game's row. An unlinked one only when its domain has a single row:
    // Reading and Language share a domain, so an unlinked skill there cannot be placed without guessing.
    public static function place(Collection $rows, Collection $byDomain, object $item): ?object
    {
        return $item->activity_id !== null
            ? $rows->firstWhere('module_domain_id', $item->module_domain_id)
            : ($byDomain->get($item->domain_id, collect())->count() === 1 ? $byDomain->get($item->domain_id)->first() : null);
    }

    // One skill with everything recorded for it; the first source present (checklist, practical, digital) sets its level.
    public static function evidence(object $item, ?object $rating, ?object $result): array
    {
        [$level, $source] = match (true) {
            isset(self::CHECKLIST_LEVELS[$rating->rating ?? '']) => [self::CHECKLIST_LEVELS[$rating->rating], 'checklist'],
            isset(self::PRACTICAL_LEVELS[$result->practical_rating ?? '']) => [self::PRACTICAL_LEVELS[$result->practical_rating], 'practical'],
            ($result->max_score ?? null) !== null => [self::scoreLevel((float) $result->score, (float) $result->max_score), 'digital'],
            default => [null, null],
        };

        return [
            'itemId' => $item->id,
            'item' => $item->item,
            'activityId' => $item->activity_id,
            'activityName' => $item->activity_name,
            'format' => $item->activity_format,
            'level' => $level,
            'source' => $source,
            // A practical that was done but not rated yet is waiting on the teacher, not a weakness.
            'status' => $level ? 'assessed' : ($result ? 'awaiting_rating' : 'not_assessed'),
            'checklist' => $rating ? ['ratingId' => $rating->id, 'rating' => $rating->rating, 'remarks' => $rating->remarks, 'at' => $rating->assessed_at] : null,
            'result' => $result ? [
                'attemptId' => $result->attempt_id,
                'score' => $result->score !== null ? (float) $result->score : null,
                'maxScore' => $result->max_score !== null ? (float) $result->max_score : null,
                'rating' => $result->practical_rating,
                'remarks' => $result->remarks,
                'playedQuarter' => (int) $result->played_quarter,
                'at' => $result->finished_at,
            ] : null,
        ];
    }

    public static function scoreLevel(float $score, float $max): string
    {
        return match (true) {
            $score * 100 >= self::DIGITAL_STRENGTH * $max => 'strength',
            $score * 100 >= self::DIGITAL_DEVELOPING * $max => 'developing',
            default => 'needs',
        };
    }

    // Strengths in the first paragraph, still-learning and needs-practice in the second, as in the printed sample.
    public static function observed(string $name, ?string $gender, array $skills): string
    {
        $pronoun = ['Male' => 'He', 'Female' => 'She'][$gender ?? ''] ?? $name;
        $subject = $name;
        $paragraphs = [];
        foreach ([['strength'], ['developing', 'needs']] as $levels) {
            $sentences = [];
            foreach ($levels as $level) {
                foreach (['checklist', 'practical', 'digital'] as $source) {
                    $items = array_column(array_filter($skills, fn ($s) => $s['level'] === $level && $s['source'] === $source), 'item');
                    foreach ($items ? self::predicates($level, $source, $items) : [] as $predicate) {
                        $sentences[] = "{$subject} {$predicate}.";
                        $subject = $pronoun;
                    }
                }
            }
            if ($sentences) {
                $paragraphs[] = implode(' ', $sentences);
            }
        }

        return $paragraphs ? implode("\n\n", $paragraphs) : self::NOT_ASSESSED;
    }

    private static function predicates(string $level, string $source, array $items): array
    {
        $plural = count($items) > 1 ? 's' : '';
        // Says where the evidence came from, never more than that.
        $where = match ($source) {
            'checklist' => ', as observed by the teacher',
            'practical' => ' during the hands-on activit'.($plural ? 'ies' : 'y'),
            'digital' => ' in the completed digital activit'.($plural ? 'ies' : 'y'),
        };
        [$verbs, $quoted] = self::split($items, fn ($item, $base) => $level === 'strength' ? lcfirst($item) : $base);

        $out = [];
        if ($verbs) {
            $out[] = match ($level) {
                'strength' => self::join($verbs),
                'developing' => 'is still learning to '.self::join($verbs),
                // A needs_help rating records that the learner needed help, so it may say so.
                'needs' => ($source === 'practical' ? 'needs help to ' : 'needs more practice to ').self::join($verbs),
            }.$where;
        }
        if ($quoted) {
            $out[] = match ($level) {
                'strength' => "shows the skill{$plural} ",
                'developing' => "is still developing the skill{$plural} ",
                'needs' => "needs more practice with the skill{$plural} ",
            }.self::join($quoted).$where;
        }

        return $out;
    }

    // Items that read as a verb phrase go into a sentence; anything else is quoted as written.
    private static function split(array $items, callable $verb): array
    {
        $verbs = [];
        $quoted = [];
        foreach ($items as $item) {
            $item = rtrim(trim($item), '.');
            $base = self::baseForm($item);
            if ($base === null) {
                $quoted[] = "\"{$item}\"";
            } else {
                $verbs[] = $verb($item, $base);
            }
        }

        return [$verbs, $quoted];
    }

    /**
     * "Matches numerals 1 to 10" -> "match numerals 1 to 10"; null when the first word is not a third-person verb.
     * ponytail: suffix rules, not a dictionary; a plural-noun item ("Shapes and colors") reads as a verb.
     */
    public static function baseForm(string $item): ?string
    {
        if (! preg_match('/^([A-Za-z]+(?:-[A-Za-z]+)*)(.*)$/s', rtrim(trim($item), '.'), $m)) {
            return null;
        }
        $word = strtolower($m[1]);
        $base = match (true) {
            $word === 'is' => 'be',
            $word === 'has' => 'have',
            strlen($word) < 3 || ! str_ends_with($word, 's') || preg_match('/(ss|us|is)$/', $word) === 1 => null,
            preg_match('/^\w+[^aeiou]ies$/', $word) === 1 => substr($word, 0, -3).'y',
            preg_match('/(ch|sh|ss|x|zz|o)es$/', $word) === 1 => substr($word, 0, -2),
            default => substr($word, 0, -1),
        };

        return $base === null ? null : $base.$m[2];
    }

    private static function join(array $parts): string
    {
        $last = array_pop($parts);

        return $parts ? implode(', ', $parts).' and '.$last : $last;
    }
}

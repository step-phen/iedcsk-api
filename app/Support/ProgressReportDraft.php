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

    // The school's codes: HA (achieved) and A (developing) are both accomplished, so both read as a strength;
    // NS (needs_support) needs practice. "/" (not_yet_observed, not yet introduced) says nothing, so the score decides.
    private const CHECKLIST_LEVELS = ['achieved' => 'strength', 'developing' => 'strength', 'needs_support' => 'needs'];

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
            // A 1-5 practical rating on the digital cut-offs: 4-5 strength, 3 developing, 1-2 needs.
            ($result->practical_rating ?? null) !== null => [self::scoreLevel((float) $result->practical_rating, ClassroomRecordController::PRACTICAL_MAX), 'practical'],
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
                'rating' => $result->practical_rating !== null ? (int) $result->practical_rating : null,
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
        // Names are stored as typed ("divine"); a sentence starts with a capital, as the class list shows them.
        $name = preg_replace_callback('/(^|\s)\S/u', fn ($m) => mb_strtoupper($m[0]), $name);
        $pronoun = ['Male' => 'He', 'Female' => 'She'][$gender ?? ''] ?? $name;
        $subject = $name;
        $paragraphs = [];
        foreach ([['strength'], ['developing', 'needs']] as $levels) {
            $sentences = [];
            foreach ($levels as $level) {
                foreach (['checklist', 'practical', 'digital'] as $source) {
                    $picked = array_filter($skills, fn ($s) => $s['level'] === $level && $s['source'] === $source);
                    // A letter skill still being learned takes in the same skill's needs-practice letters,
                    // so "Xx and Yy" and "Vv and Ww" read as one sentence, then one line says they need practice.
                    $alsoNeeds = false;
                    if ($level === 'developing') {
                        $templates = array_filter(array_map(self::letterTemplate(...), array_column($picked, 'item')));
                        foreach ($skills as $i => $s) {
                            if ($s['level'] === 'needs' && $s['source'] === $source && in_array(self::letterTemplate($s['item']), $templates, true)) {
                                $picked[] = $s;
                                unset($skills[$i]);
                                $alsoNeeds = true;
                            }
                        }
                    }
                    $items = array_column($picked, 'item');
                    // Merged letter skills are still separate games, so "activities" counts the games, not the sentences.
                    $games = array_unique(array_filter(array_column($picked, 'activityId')));
                    foreach ($items ? self::predicates($level, $source, self::mergeLetters($items), $games ? count($games) : count($items)) : [] as $predicate) {
                        $sentences[] = "{$subject} {$predicate}.";
                        $subject = $pronoun;
                    }
                    if ($alsoNeeds) {
                        $sentences[] = "{$subject} ".($source === 'practical' ? 'needs help' : 'needs more practice').' with these letters.';
                    }
                }
            }
            if ($sentences) {
                $paragraphs[] = implode(' ', $sentences);
            }
        }

        return $paragraphs ? implode("\n\n", $paragraphs) : self::NOT_ASSESSED;
    }

    private static function predicates(string $level, string $source, array $items, int $activities): array
    {
        $plural = count($items) > 1 ? 's' : '';
        $activity = $activities > 1 ? 'activities' : 'activity';
        // Says where the evidence came from, never more than that.
        $where = match ($source) {
            'checklist' => ', as observed by the teacher',
            'practical' => " during the hands-on {$activity}",
            'digital' => " in the completed digital {$activity}",
        };
        [$verbs, $quoted] = self::split($items, fn ($item, $base) => $level === 'strength' ? lcfirst($item) : $base);

        $out = [];
        if ($verbs) {
            $out[] = match ($level) {
                'strength' => self::join($verbs),
                'developing' => 'is still learning to '.self::join($verbs),
                // A low practical rating (1-2) records that the learner needed help, so it may say so.
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

    // A run of letter pairs in a skill: "Xx and Yy", "Aa, Ee, Ii, Oo, Uu".
    private static function letterList(): string
    {
        $pair = implode('|', array_map(fn ($c) => $c.strtolower($c), range('A', 'Z')));

        return "/\b(?:{$pair})(?:(?:,? and |, )(?:{$pair}))*\b/";
    }

    // The skill with its letters taken out, so skills that differ only in their letters match; null without letters.
    private static function letterTemplate(string $item): ?string
    {
        $item = rtrim(trim($item), '.');

        return preg_match(self::letterList(), $item) ? preg_replace(self::letterList(), '{letters}', $item, 1) : null;
    }

    // "... Xx and Yy after ..." + "... Vv and Ww after ..." -> "... Xx, Yy, Vv, and Ww after ..."; other items as they are.
    private static function mergeLetters(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $item = rtrim(trim($item), '.');
            $template = self::letterTemplate($item);
            if ($template === null) {
                $groups[$item] = null;

                continue;
            }
            preg_match(self::letterList(), $item, $m);
            $groups[$template] = [...$groups[$template] ?? [], ...preg_split('/,? and |, /', $m[0])];
        }

        $out = [];
        foreach ($groups as $text => $letters) {
            $out[] = $letters === null ? $text : str_replace('{letters}', self::join(array_values(array_unique($letters))), $text);
        }

        return $out;
    }

    private static function join(array $parts): string
    {
        $last = array_pop($parts);

        // Serial comma for three or more: "Xx, Yy, Vv, and Ww".
        return $parts ? implode(', ', $parts).(count($parts) > 1 ? ',' : '').' and '.$last : $last;
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// "Say and Point: Aa and Ee" game for Language, Q1 Week 6 Monday; run with: php artisan db:seed --class=SayAndPointAaEeSeeder
class SayAndPointAaEeSeeder extends Seeder
{
    private const CODE = 'LG-Q1W6-01';

    private const CHECKLIST_ITEM = 'Tells Aa from Ee and says each letter name';

    // The letter on each round's cookie, in play order; the answer is the friend who eats it (a = Ant, e = Elephant).
    private const COOKIES = ['A', 'e', 'E', 'a', 'e'];

    public function run(): void
    {
        // Lessons are teacher-made, so this attaches to the existing Language Q1 Week 6 Monday lesson.
        $lessonId = DB::table('lessons')
            ->join('module_domains', 'module_domains.id', '=', 'lessons.module_domain_id')
            ->join('modules', 'modules.id', '=', 'module_domains.module_id')
            ->where(['modules.code' => 'language', 'lessons.quarter_number' => 1, 'lessons.week_number' => 6, 'lessons.day_of_week' => 1, 'lessons.is_active' => true])
            ->orderBy('lessons.sequence_order')
            ->value('lessons.id');
        if (! $lessonId) {
            throw new RuntimeException('Add the Language lesson for Quarter 1, Week 6, Monday first.');
        }

        DB::transaction(function () use ($lessonId) {
            DB::table('activities')->updateOrInsert(['activity_code' => self::CODE], [
                'lesson_id' => $lessonId,
                'name' => 'Say and Point: Aa and Ee',
                'description' => 'Tell Aa from Ee and say each letter name.',
                'instructions' => 'Feed each letter cookie to the friend with the same letter, then say the letter name.',
                'activity_format' => 'digital',
                'sequence_order' => 1,
            ]);
            $activityId = DB::table('activities')->where('activity_code', self::CODE)->value('id');

            DB::table('digital_activity_details')->updateOrInsert(['activity_id' => $activityId], [
                'game_type' => 'tap_choice',
                'configuration' => '{}',
                'max_score' => count(self::COOKIES),
            ]);

            // Cleared and re-added, since kept rows would clash with new question_order values; keyed by round, since a letter comes up more than once.
            DB::table('activity_questions')->where('activity_id', $activityId)->delete();
            foreach (self::COOKIES as $i => $letter) {
                DB::table('activity_questions')->insert([
                    'activity_id' => $activityId,
                    'question_key' => 'round-'.($i + 1),
                    'question_order' => $i + 1,
                    'question_data' => json_encode(['letter' => $letter]),
                    'correct_answer' => json_encode(['answer' => strtolower($letter)]),
                ]);
            }

            // The skill this game practises, on the Developmental Checklist under Reading and Language for Quarter 1.
            $domainId = DB::table('developmental_domains')->where('code', 'reading_language')->value('id');
            $item = ['domain_id' => $domainId, 'grade_level' => 'Kindergarten', 'quarter_number' => 1, 'item' => self::CHECKLIST_ITEM];
            // Matched by activity first, so renaming the item updates its row instead of adding a second one.
            $existing = DB::table('developmental_checklist_items')->where('activity_id', $activityId)->value('id')
                ?? DB::table('developmental_checklist_items')->where($item)->value('id');
            if ($existing) {
                DB::table('developmental_checklist_items')->where('id', $existing)->update(['activity_id' => $activityId, 'item' => self::CHECKLIST_ITEM, 'updated_at' => now()]);
            } else {
                DB::table('developmental_checklist_items')->insert($item + [
                    'activity_id' => $activityId,
                    'display_order' => DB::table('developmental_checklist_items')->where(['domain_id' => $domainId, 'quarter_number' => 1])->max('display_order') + 1,
                ]);
            }
        });
    }
}

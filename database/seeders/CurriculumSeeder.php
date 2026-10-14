<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurriculumSeeder extends Seeder
{
    private const DOMAINS = [
        'reading_language' => 'Reading and Language Development',
        'writing_readiness' => 'Writing Readiness',
        'gross_motor' => 'Gross Motor Development',
        'arts_crafts' => 'Arts and Crafts',
        'health_habits' => 'Health, Works, Habits and Attitude',
        'math_readiness' => 'Math Readiness',
    ];

    // Module code => [name, domain codes]; MAPEH maps to three domains.
    private const MODULES = [
        'reading' => ['Reading', ['reading_language']],
        'language' => ['Language', ['reading_language']],
        'writing' => ['Writing', ['writing_readiness']],
        'mapeh' => ['MAPEH', ['gross_motor', 'arts_crafts', 'health_habits']],
        'math' => ['Math', ['math_readiness']],
    ];

    public function run(): void
    {
        $order = 0;
        foreach (self::DOMAINS as $code => $name) {
            DB::table('developmental_domains')->updateOrInsert(['code' => $code], ['name' => $name, 'display_order' => ++$order]);
        }

        foreach (self::MODULES as $code => [$name, $domainCodes]) {
            DB::table('modules')->updateOrInsert(['grade_level' => 'Kindergarten', 'code' => $code], ['name' => $name]);
            $moduleId = DB::table('modules')->where(['grade_level' => 'Kindergarten', 'code' => $code])->value('id');

            foreach ($domainCodes as $domainCode) {
                DB::table('module_domains')->insertOrIgnore([
                    'module_id' => $moduleId,
                    'domain_id' => DB::table('developmental_domains')->where('code', $domainCode)->value('id'),
                ]);
            }
        }
    }
}

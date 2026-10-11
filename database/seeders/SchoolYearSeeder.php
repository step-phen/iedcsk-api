<?php

namespace Database\Seeders;

use App\Models\SchoolYear;
use Illuminate\Database\Seeder;

class SchoolYearSeeder extends Seeder
{
    public function run(): void
    {
        // Real dates are entered by the teacher, so seeding is skipped until .env provides them.
        $name = env('SEED_SCHOOL_YEAR_NAME');
        $start = env('SEED_SCHOOL_YEAR_START');
        $end = env('SEED_SCHOOL_YEAR_END');

        if (! $name || ! $start || ! $end) {
            $this->command?->warn('Skipped school year: set SEED_SCHOOL_YEAR_NAME, SEED_SCHOOL_YEAR_START and SEED_SCHOOL_YEAR_END in .env.');

            return;
        }

        SchoolYear::updateOrCreate(['name' => $name], ['start_date' => $start, 'end_date' => $end, 'status' => 'active']);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

abstract class Controller
{
    // A section's class_schedules joined to section_modules, limited to the timetable that
    // applies today: an active module assignment, an active schedule, and today (Asia/Manila)
    // inside effective_from..effective_until, both ends inclusive. The caller checks ownership.
    // Shared by the Modules board and Today's Schedule so both follow the same rule.
    protected function usableSchedules(int $sectionId): Builder
    {
        // Date columns compared with a Y-m-d string, so the app timezone can't shift the day.
        $today = Carbon::now('Asia/Manila')->toDateString();

        return DB::table('class_schedules')
            ->join('section_modules', 'section_modules.id', '=', 'class_schedules.section_module_id')
            ->where('section_modules.section_id', $sectionId)
            ->where('section_modules.is_active', true)
            ->where('class_schedules.is_active', true)
            ->where('class_schedules.effective_from', '<=', $today)
            ->where('class_schedules.effective_until', '>=', $today);
    }
}

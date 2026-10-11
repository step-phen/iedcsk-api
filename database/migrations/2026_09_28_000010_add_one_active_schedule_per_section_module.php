<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // A module meets at the same start/end time on all of its days within a
    // section, so each section_module has at most one active schedule. An
    // inactive row stays allowed for history (e.g. a mid-year time change:
    // deactivate the old row, insert the new one).
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX class_schedules_one_active_per_section_module ON class_schedules (section_module_id) WHERE is_active');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS class_schedules_one_active_per_section_module');
    }
};

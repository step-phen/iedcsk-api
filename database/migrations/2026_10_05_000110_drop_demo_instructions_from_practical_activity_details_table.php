<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The steps learners follow live in activities.instructions; the demonstration is the video itself.
    public function up(): void
    {
        DB::statement('ALTER TABLE practical_activity_details DROP COLUMN demo_instructions');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE practical_activity_details ADD COLUMN demo_instructions text NULL');
    }
};

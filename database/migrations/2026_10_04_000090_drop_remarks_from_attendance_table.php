<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Attendance is only a present/late/absent mark; nothing records a note with it.
    public function up(): void
    {
        DB::statement('ALTER TABLE attendance DROP COLUMN remarks');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE attendance ADD COLUMN remarks text NULL');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Sections are ordered by id for creation order; their creation and edit times are not tracked.
    public function up(): void
    {
        DB::statement('ALTER TABLE sections DROP COLUMN created_at, DROP COLUMN updated_at');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sections ADD COLUMN created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }
};

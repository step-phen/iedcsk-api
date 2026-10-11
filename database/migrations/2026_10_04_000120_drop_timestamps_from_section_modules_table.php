<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Section modules are fixed links between a section and its modules; their creation and edit times are not tracked.
    public function up(): void
    {
        DB::statement('ALTER TABLE section_modules DROP COLUMN created_at, DROP COLUMN updated_at');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE section_modules ADD COLUMN created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }
};

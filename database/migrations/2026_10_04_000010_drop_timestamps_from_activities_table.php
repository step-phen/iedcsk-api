<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Activities are curriculum definitions; their creation and edit times are not tracked.
    public function up(): void
    {
        DB::statement('ALTER TABLE activities DROP COLUMN created_at, DROP COLUMN updated_at');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE activities ADD COLUMN created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }
};

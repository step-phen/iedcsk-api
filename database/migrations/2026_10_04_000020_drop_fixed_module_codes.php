<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Modules are curriculum data, so a new subject can be added without a migration.
    public function up(): void
    {
        DB::statement('ALTER TABLE modules DROP CONSTRAINT IF EXISTS modules_code_check');
    }

    // Fails while a module outside the original five exists; remove it first.
    public function down(): void
    {
        DB::statement("ALTER TABLE modules ADD CONSTRAINT modules_code_check CHECK (code IN ('reading', 'language', 'writing', 'mapeh', 'math'))");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // A module is identified by its code and name; it carries no description.
    public function up(): void
    {
        DB::statement('ALTER TABLE modules DROP COLUMN description');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE modules ADD COLUMN description text NULL');
    }
};

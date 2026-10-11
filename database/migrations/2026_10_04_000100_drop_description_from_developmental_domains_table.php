<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // A domain is shown by its name only; nothing reads or writes a description.
    public function up(): void
    {
        DB::statement('ALTER TABLE developmental_domains DROP COLUMN description');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE developmental_domains ADD COLUMN description text NULL');
    }
};

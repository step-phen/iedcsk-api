<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The reason on an excuse slip, kept on the attendance day it excuses.
    public function up(): void
    {
        DB::statement('ALTER TABLE attendance ADD COLUMN excuse_reason varchar(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE attendance DROP COLUMN excuse_reason');
    }
};

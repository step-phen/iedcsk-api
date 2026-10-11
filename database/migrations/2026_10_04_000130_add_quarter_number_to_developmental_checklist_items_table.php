<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Each quarter teaches different lessons and activities, so each checklist item belongs to one quarter; existing items start in Quarter 1.
    public function up(): void
    {
        DB::statement('ALTER TABLE developmental_checklist_items ADD COLUMN quarter_number smallint NOT NULL DEFAULT 1');
        DB::statement('ALTER TABLE developmental_checklist_items ALTER COLUMN quarter_number DROP DEFAULT');
        DB::statement('ALTER TABLE developmental_checklist_items ADD CONSTRAINT developmental_checklist_items_quarter_number_check CHECK (quarter_number BETWEEN 1 AND 4)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE developmental_checklist_items DROP COLUMN quarter_number');
    }
};

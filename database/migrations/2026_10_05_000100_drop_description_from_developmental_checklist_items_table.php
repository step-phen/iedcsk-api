<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Nothing reads an item's description; the activity it is practised in now comes from activity_id.
    public function up(): void
    {
        DB::statement('ALTER TABLE developmental_checklist_items DROP COLUMN description');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE developmental_checklist_items ADD COLUMN description text');
    }
};

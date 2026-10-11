<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The activity a checklist item's skill is practised in, shown under the item; optional for skills not tied to one game.
    public function up(): void
    {
        Schema::table('developmental_checklist_items', function (Blueprint $table) {
            $table->foreignId('activity_id')->nullable()->index()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('developmental_checklist_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_id');
        });
    }
};

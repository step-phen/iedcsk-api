<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('developmental_checklist_items', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('domain_id')->index()->constrained('developmental_domains')->restrictOnDelete();
            $table->text('item');
            $table->text('description')->nullable();
            $table->string('grade_level', 50);
            $table->integer('display_order');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement('ALTER TABLE developmental_checklist_items ADD CONSTRAINT developmental_checklist_items_display_order_check CHECK (display_order > 0)');

    }

    public function down(): void
    {
        Schema::dropIfExists('developmental_checklist_items');
    }
};

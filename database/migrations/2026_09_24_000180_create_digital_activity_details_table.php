<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_activity_details', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('activity_id')->unique()->constrained()->restrictOnDelete();
            $table->string('game_type', 100);
            $table->jsonb('configuration')->default('{}');
            $table->decimal('max_score', 10, 2);
            $table->decimal('passing_score', 10, 2)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement('ALTER TABLE digital_activity_details ADD CONSTRAINT digital_activity_details_max_score_check CHECK (max_score > 0)');
        DB::statement('ALTER TABLE digital_activity_details ADD CONSTRAINT digital_activity_details_passing_score_check CHECK (passing_score IS NULL OR passing_score BETWEEN 0 AND max_score)');

    }

    public function down(): void
    {
        Schema::dropIfExists('digital_activity_details');
    }
};

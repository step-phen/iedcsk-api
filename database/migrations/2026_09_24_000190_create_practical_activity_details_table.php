<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practical_activity_details', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('activity_id')->unique()->constrained()->restrictOnDelete();
            $table->text('demo_video_url')->nullable();
            $table->text('demo_instructions')->nullable();
            $table->text('teacher_guidelines');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practical_activity_details');
    }
};

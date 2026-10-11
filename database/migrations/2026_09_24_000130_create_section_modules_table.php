<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_modules', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('module_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->index()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);

            $table->unique(['section_id', 'module_id']);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_modules');
    }
};

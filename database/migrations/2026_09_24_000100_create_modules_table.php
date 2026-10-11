<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('grade_level', 50);
            $table->boolean('is_active')->default(true);

            $table->unique(['grade_level', 'code']);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement("ALTER TABLE modules ADD CONSTRAINT modules_code_check CHECK (code IN ('reading', 'language', 'writing', 'mapeh', 'math'))");

    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};

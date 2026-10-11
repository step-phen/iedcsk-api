<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learners', function (Blueprint $table) {
            $table->id()->generatedAs();
            // Optional Learner Reference Number; many kindergarteners have none yet.
            $table->string('lrn', 12)->nullable()->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('extension_name', 10)->nullable();
            $table->date('birth_date');
            $table->string('gender', 30)->nullable();
            $table->text('avatar_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement("ALTER TABLE learners ADD CONSTRAINT learners_gender_check CHECK (gender IN ('Male', 'Female'))");

    }

    public function down(): void
    {
        Schema::dropIfExists('learners');
    }
};

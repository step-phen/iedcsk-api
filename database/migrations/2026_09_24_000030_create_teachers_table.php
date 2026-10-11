<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->string('name', 150);
            $table->string('username', 100)->unique();
            $table->text('password_hash');
            $table->string('email', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement('ALTER TABLE teachers ADD CONSTRAINT teachers_username_lowercase_check CHECK (username = lower(username))');

    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};

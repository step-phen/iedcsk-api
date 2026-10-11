<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // One row per finished assignment; names are snapshotted so the bell needs no joins.
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('teacher_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->unique()->constrained('learner_activity_assignments')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('learner_name', 201);
            $table->string('activity_name')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_kind_check CHECK (kind IN ('finished', 'needs_rating'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

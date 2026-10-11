<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Audit trail of class handovers; a pending row waits for the incoming teacher to sign in and accept.
    public function up(): void
    {
        Schema::create('teacher_handovers', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('from_teacher_id')->index()->constrained('teachers')->restrictOnDelete();
            $table->foreignId('to_teacher_id')->index()->constrained('teachers')->restrictOnDelete();
            $table->string('status', 20);
            $table->jsonb('section_ids')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
        });
        DB::statement("ALTER TABLE teacher_handovers ADD CONSTRAINT teacher_handovers_status_check CHECK (status IN ('pending', 'accepted', 'cancelled'))");
        DB::statement('ALTER TABLE teacher_handovers ADD CONSTRAINT teacher_handovers_distinct_check CHECK (from_teacher_id <> to_teacher_id)');
        DB::statement("CREATE UNIQUE INDEX teacher_handovers_one_pending_from ON teacher_handovers (from_teacher_id) WHERE status = 'pending'");
        DB::statement("CREATE UNIQUE INDEX teacher_handovers_one_pending_to ON teacher_handovers (to_teacher_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_handovers');
    }
};

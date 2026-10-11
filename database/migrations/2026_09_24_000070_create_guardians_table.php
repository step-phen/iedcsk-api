<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('learner_id')->index()->constrained()->cascadeOnDelete();
            $table->string('guardian_name', 100);
            $table->string('contact_number', 11);
            $table->string('relationship_to_learner', 50);
            $table->string('guardian_email', 150)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};

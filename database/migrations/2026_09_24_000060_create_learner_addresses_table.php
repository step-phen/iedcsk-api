<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_addresses', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('learner_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('house_street')->nullable();
            $table->string('barangay', 100)->nullable();
            $table->string('city_municipality', 100)->nullable();
            $table->foreignId('province_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('zip_code', 10)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_addresses');
    }
};

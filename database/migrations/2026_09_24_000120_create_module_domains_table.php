<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_domains', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->foreignId('module_id')->constrained()->restrictOnDelete();
            $table->foreignId('domain_id')->index()->constrained('developmental_domains')->restrictOnDelete();

            $table->unique(['module_id', 'domain_id']);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_domains');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('developmental_domains', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->string('code', 50)->unique();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->smallInteger('display_order');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement('ALTER TABLE developmental_domains ADD CONSTRAINT developmental_domains_display_order_check CHECK (display_order > 0)');

    }

    public function down(): void
    {
        Schema::dropIfExists('developmental_domains');
    }
};

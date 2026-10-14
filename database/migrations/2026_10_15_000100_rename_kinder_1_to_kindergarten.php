<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The grade level is plain text on these tables, so renaming it in place keeps every row id and link.
    private const TABLES = ['sections', 'modules', 'developmental_checklist_items'];

    public function up(): void
    {
        $this->rename('Kinder 1', 'Kindergarten');
    }

    public function down(): void
    {
        $this->rename('Kindergarten', 'Kinder 1');
    }

    private function rename(string $from, string $to): void
    {
        // One transaction: a unique-key clash on any table undoes the whole rename, never half of it.
        DB::transaction(function () use ($from, $to) {
            foreach (self::TABLES as $table) {
                DB::table($table)->where('grade_level', $from)->update(['grade_level' => $to]);
            }
        });
    }
};

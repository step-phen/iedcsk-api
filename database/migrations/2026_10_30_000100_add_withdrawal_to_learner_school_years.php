<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A withdrawal closes one school-year enrollment on a given day, with an optional reason; the row and every record
    // tied to it stay. A withdrawn row must carry its date, so rows already marked withdrawn (set by hand, since the app
    // never wrote that status) would need a real date first: the migration stops rather than guess one.
    public function up(): void
    {
        $withdrawn = DB::table('learner_school_years')->where('status', 'withdrawn')->orderBy('id')->pluck('id');
        if ($withdrawn->isNotEmpty()) {
            throw new RuntimeException(
                'learner_school_years rows already marked withdrawn have no withdrawal date: ids '.$withdrawn->implode(', ')
                .'. Record each real date before running this migration.'
            );
        }

        Schema::table('learner_school_years', function (Blueprint $table) {
            $table->date('withdrawn_on')->nullable();
            $table->string('withdrawal_reason', 255)->nullable();
        });
        DB::statement("ALTER TABLE learner_school_years ADD CONSTRAINT learner_school_years_withdrawn_on_check CHECK ((status = 'withdrawn') = (withdrawn_on IS NOT NULL))");
        DB::statement('ALTER TABLE learner_school_years ADD CONSTRAINT learner_school_years_withdrawn_after_enrolled_check CHECK (withdrawn_on IS NULL OR withdrawn_on >= enrolled_on)');
        DB::statement("ALTER TABLE learner_school_years ADD CONSTRAINT learner_school_years_withdrawal_reason_check CHECK (withdrawal_reason IS NULL OR status = 'withdrawn')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE learner_school_years DROP CONSTRAINT learner_school_years_withdrawal_reason_check');
        DB::statement('ALTER TABLE learner_school_years DROP CONSTRAINT learner_school_years_withdrawn_after_enrolled_check');
        DB::statement('ALTER TABLE learner_school_years DROP CONSTRAINT learner_school_years_withdrawn_on_check');

        Schema::table('learner_school_years', function (Blueprint $table) {
            $table->dropColumn(['withdrawn_on', 'withdrawal_reason']);
        });
    }
};

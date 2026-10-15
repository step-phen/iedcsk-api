<?php

namespace Tests;

use App\Models\Quarter;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Sets up a school year's sections the way activation does (defaults: both, in the active year).
     *
     * @return array<string, int> section name => id
     */
    protected function setUpSections(Teacher $teacher, array $names = ['Joy', 'Faith'], ?SchoolYear $year = null): array
    {
        $year ??= SchoolYear::active();
        Section::setUp($year, $teacher, $names);

        return Section::where('school_year_id', $year->id)->pluck('id', 'name')->all();
    }

    /**
     * Asks a second connection for the quarter lock (Quarter::lockState). RefreshDatabase keeps each
     * test in one transaction, so once a request has taken the lock this stays false until the test ends.
     */
    protected function quarterLockFree(): bool
    {
        config(['database.connections.lock_probe' => config('database.connections.'.config('database.default'))]);

        return DB::connection('lock_probe')->selectOne('SELECT pg_try_advisory_xact_lock(?) AS free', [Quarter::STATE_LOCK])->free;
    }
}

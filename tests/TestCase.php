<?php

namespace Tests;

use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
}

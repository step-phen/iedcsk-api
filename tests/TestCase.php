<?php

namespace Tests;

use App\Http\Requests\LoginRequest;
use App\Models\Quarter;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

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

    /** Posts a browser login from one client IP, sending only the given device cookie and headers. */
    protected function loginFrom(string $ip, string $username, string $password = 'wrong', ?string $deviceCookie = null, array $headers = []): TestResponse
    {
        // Each login is a fresh browser: the test app would otherwise keep the last session's user, guards and queued cookies.
        $this->app['auth']->forgetGuards();
        $this->app['session.store']->flush();
        $this->app['cookie']->flushQueuedCookies();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        if ($deviceCookie !== null) {
            $this->withUnencryptedCookie(LoginRequest::DEVICE_COOKIE, $deviceCookie);
        }

        return $this->withCredentials()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson(
            '/login',
            ['username' => $username, 'password' => $password],
            ['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/'] + $headers,
        );
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

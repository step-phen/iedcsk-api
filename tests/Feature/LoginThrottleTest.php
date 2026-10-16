<?php

namespace Tests\Feature;

use App\Http\Requests\LoginRequest;
use App\Models\Teacher;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const A = '192.0.2.1';

    private const B = '192.0.2.2';

    private const C = '192.0.2.3';

    private const D = '192.0.2.4';

    private Teacher $tina;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'secret123']);
    }

    public static function stores(): array
    {
        return ['array cache' => ['array'], 'database cache' => ['database']];
    }

    private function useStore(string $store): void
    {
        config(['cache.default' => $store]);
    }

    private function userKey(string $username = 'tina'): string
    {
        return LoginRequest::key('user', $username);
    }

    private function userIpKey(string $ip, string $username = 'tina'): string
    {
        return LoginRequest::key('user-ip', $ip.'|'.$username);
    }

    private function failTimes(int $times, string $ip, string $username = 'tina'): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->loginFrom($ip, $username)->assertStatus(422);
        }
    }

    private function assertRetryAfter(TestResponse $response, int $min, int $max): void
    {
        $retryAfter = (int) $response->headers->get('Retry-After');
        $this->assertTrue($retryAfter >= $min && $retryAfter <= $max, "Retry-After {$retryAfter} is outside {$min}-{$max}.");
    }

    private function countAttempting(): \ArrayObject
    {
        $count = new \ArrayObject(['n' => 0]);
        Event::listen(Attempting::class, function () use ($count) {
            $count['n']++;
        });

        return $count;
    }

    // T1 (and C6 on the database store): the sixth failure from one IP is refused before any password check.
    #[DataProvider('stores')]
    public function test_username_and_ip_limit_blocks_before_the_password_check(string $store): void
    {
        $this->useStore($store);
        $this->failTimes(5, self::A);
        if ($store === 'database') {
            $this->assertGreaterThan(0, DB::table('cache')->count());
        }

        $attempting = $this->countAttempting();
        config(['app.debug' => false]);
        $this->assertRetryAfter($this->loginFrom(self::A, 'tina')->assertStatus(429)->assertExactJson(['message' => 'Too Many Attempts.']), 1, 60);
        $this->loginFrom(self::A, 'tina', 'secret123')->assertStatus(429);

        $this->assertSame(0, $attempting['n']);
        $this->assertEquals(5, RateLimiter::attempts($this->userIpKey(self::A)));
    }

    // T2: the existing per-IP limit still caps all requests from one address.
    public function test_per_ip_limit_counts_every_request(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->loginFrom(self::A, "u{$i}")->assertStatus(422);
        }

        $this->assertRetryAfter($this->loginFrom(self::A, 'u11')->assertStatus(429), 1, 60);
    }

    // T3: ten failures spread over IPs block the account everywhere until the 15-minute window ends.
    public function test_account_wide_limit_spans_ips_and_expires(): void
    {
        $this->failTimes(5, self::A);
        $this->failTimes(5, self::B);

        $this->assertRetryAfter($this->loginFrom(self::C, 'tina', 'secret123')->assertStatus(429), 61, 900);

        $this->travel(901)->seconds();
        $this->loginFrom(self::C, 'tina', 'secret123')->assertOk();
    }

    // T4: successes take no failed-login quota and do not erase earlier failures.
    public function test_successes_neither_consume_quota_nor_erase_failures(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->loginFrom(self::A, 'tina', 'secret123')->assertOk();
        }
        $this->assertEquals(0, RateLimiter::attempts($this->userKey()));

        $this->failTimes(4, self::A);
        $this->loginFrom(self::A, 'tina', 'secret123')->assertOk();
        $this->assertEquals(4, RateLimiter::attempts($this->userKey()));
        $this->assertEquals(4, RateLimiter::attempts($this->userIpKey(self::A)));

        $this->failTimes(5, self::B);
        $this->failTimes(1, self::C);
        $this->loginFrom(self::D, 'tina')->assertStatus(429);
    }

    // T5: one blocked username and IP leaves other IPs and other usernames alone.
    public function test_buckets_are_isolated(): void
    {
        $this->failTimes(5, self::A);
        $this->loginFrom(self::A, 'tina')->assertStatus(429);

        $this->loginFrom(self::B, 'tina')->assertStatus(422);
        $this->loginFrom(self::A, 'omar')->assertStatus(422);
    }

    // T6: case variants of one username share its buckets.
    public function test_username_case_variants_share_a_bucket(): void
    {
        foreach (['Tina', 'TINA', 'tina', 'tInA', 'TiNa'] as $variant) {
            $this->loginFrom(self::A, $variant)->assertStatus(422);
        }

        $this->loginFrom(self::A, 'TINA')->assertStatus(429);
    }

    // T7: an unknown username and a wrong password look and throttle exactly alike.
    public function test_unknown_and_known_usernames_are_indistinguishable(): void
    {
        // Production bodies: debug mode would add a stack trace to the 429.
        config(['app.debug' => false]);
        for ($i = 1; $i <= 6; $i++) {
            $ghost = $this->loginFrom(self::A, 'ghost');
            $known = $this->loginFrom(self::B, 'tina');

            $this->assertSame($i <= 5 ? 422 : 429, $ghost->status());
            $this->assertSame($ghost->status(), $known->status());
            $this->assertSame($ghost->getContent(), $known->getContent());
        }
    }

    // T8: behind the local nginx each real client gets its own bucket, and outsiders cannot spoof one.
    public function test_limits_key_on_the_trusted_client_ip(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->loginFrom('127.0.0.1', "u{$i}", headers: ['X-Forwarded-For' => '203.0.113.5'])->assertStatus(422);
        }
        $this->loginFrom('127.0.0.1', 'u11', headers: ['X-Forwarded-For' => '203.0.113.5'])->assertStatus(429);
        $this->loginFrom('127.0.0.1', 'u12', headers: ['X-Forwarded-For' => '203.0.113.6'])->assertStatus(422);

        for ($i = 1; $i <= 10; $i++) {
            $this->loginFrom('192.168.1.50', "v{$i}", headers: ['X-Forwarded-For' => "198.51.100.{$i}"])->assertStatus(422);
        }
        $this->loginFrom('192.168.1.50', 'v11', headers: ['X-Forwarded-For' => '198.51.100.11'])->assertStatus(429);
    }

    // T9: a malformed username is a validation error, never a server error.
    public function test_array_username_is_rejected_cleanly(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::A])
            ->postJson('/login', ['username' => ['tina'], 'password' => 'x'], ['Origin' => 'http://localhost:3000'])
            ->assertStatus(422)->assertJsonValidationErrors('username');

        $this->assertEquals(0, RateLimiter::attempts($this->userKey()));
    }

    // T10: a failed login is logged with a keyed username hash and never the password or raw username.
    public function test_failed_login_log_holds_no_secrets(): void
    {
        Log::spy();

        $this->loginFrom(self::A, 'tina', 'hunter2-secret');

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) {
            $encoded = json_encode($context);

            return $message === 'Teacher login failed'
                && array_keys($context) === ['ip', 'teacher_id', 'username_hash']
                && $context['ip'] === self::A
                && $context['teacher_id'] === $this->tina->id
                && $context['username_hash'] === LoginRequest::usernameHash('tina')
                && ! str_contains($encoded, 'hunter2') && ! str_contains($encoded, 'tina');
        });
    }

    // T11: a username-limit refusal is logged without secrets.
    public function test_throttled_login_is_logged(): void
    {
        $this->failTimes(5, self::A);
        Log::spy();

        $this->loginFrom(self::A, 'tina', 'hunter2-secret')->assertStatus(429);

        Log::shouldHaveReceived('warning')->once()
            ->with('Teacher login throttled', ['ip' => self::A, 'username_hash' => LoginRequest::usernameHash('tina')]);
    }

    // T12: the reset command unlocks only the named teacher.
    public function test_reset_command_is_targeted(): void
    {
        Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'secret123']);
        $this->failTimes(5, self::A);
        $this->failTimes(5, self::B);
        $this->failTimes(5, self::C, 'omar');
        $this->failTimes(5, self::D, 'omar');

        $this->artisan('login:reset', ['username' => 'TINA', '--ip' => self::A])->assertSuccessful();

        $this->assertEquals(0, RateLimiter::attempts($this->userKey()));
        $this->assertEquals(0, RateLimiter::attempts($this->userIpKey(self::A)));
        $this->assertEquals(5, RateLimiter::attempts($this->userIpKey(self::B)));
        $this->loginFrom(self::A, 'tina', 'secret123')->assertOk();
        $this->loginFrom('192.0.2.9', 'omar', 'secret123')->assertStatus(429);
    }

    // Concurrency tests below are simulated interleavings inside one process, not parallel requests.

    // C1: this request's slot is counted before its password is checked, so a parallel request would see it.
    public function test_simulated_reservation_precedes_the_password_check(): void
    {
        $seen = [];
        Event::listen(Attempting::class, function () use (&$seen) {
            $seen = [RateLimiter::attempts($this->userIpKey(self::A)), RateLimiter::attempts($this->userKey())];
        });

        $this->loginFrom(self::A, 'tina')->assertStatus(422);

        $this->assertEquals([1, 1], $seen);
    }

    // C2 (and C6): a parallel request taking the last slot after this one's check makes this one back out with a 429.
    #[DataProvider('stores')]
    public function test_simulated_race_past_the_limit_is_rolled_back(string $store): void
    {
        $this->useStore($store);
        $this->failTimes(5, self::A);
        $this->failTimes(4, self::B);

        $injected = false;
        Event::listen(CacheHit::class, function (CacheHit $event) use (&$injected) {
            if (! $injected && $event->key === $this->userKey()) {
                $injected = true;
                RateLimiter::hit($this->userKey(), 900);
            }
        });
        $attempting = $this->countAttempting();

        $this->loginFrom(self::C, 'tina', 'secret123')->assertStatus(429);

        $this->assertTrue($injected);
        $this->assertSame(0, $attempting['n']);
        $this->assertEquals(10, RateLimiter::attempts($this->userKey()));
        $this->assertEquals(0, RateLimiter::attempts($this->userIpKey(self::C)));
    }

    // C3 (and C6): a success hands back only its own slot, so a failure that lands meanwhile stays counted.
    #[DataProvider('stores')]
    public function test_simulated_success_keeps_a_concurrent_failure(string $store): void
    {
        $this->useStore($store);
        Event::listen(Attempting::class, function () {
            RateLimiter::hit($this->userIpKey(self::A), 60);
            RateLimiter::hit($this->userKey(), 900);
        });

        $this->loginFrom(self::A, 'tina', 'secret123')->assertOk();

        $this->assertEquals(1, RateLimiter::attempts($this->userIpKey(self::A)));
        $this->assertEquals(1, RateLimiter::attempts($this->userKey()));
    }

    // C4: windows that expire mid-request are not driven negative, so the next window grants no extra attempt.
    public function test_simulated_expiry_mid_request_leaves_no_negative_count(): void
    {
        Event::listen(Attempting::class, function () {
            Carbon::setTestNow(now()->addSeconds(901));
        });

        $this->loginFrom(self::A, 'tina', 'secret123')->assertOk();
        Event::forget(Attempting::class);

        $this->assertEquals(0, RateLimiter::attempts($this->userKey()));
        $this->assertEquals(0, RateLimiter::attempts($this->userIpKey(self::A)));

        $this->failTimes(5, self::A);
        $this->failTimes(5, self::B);
        $this->loginFrom(self::C, 'tina')->assertStatus(429);
    }

    // C5: an overflow on the first bucket undoes only this request's own hit and never touches the second bucket.
    public function test_simulated_rollback_touches_only_its_own_slots(): void
    {
        $this->failTimes(4, self::A);

        $injected = false;
        Event::listen(CacheHit::class, function (CacheHit $event) use (&$injected) {
            if (! $injected && $event->key === $this->userIpKey(self::A)) {
                $injected = true;
                RateLimiter::hit($this->userIpKey(self::A), 60);
            }
        });

        $this->loginFrom(self::A, 'tina', 'secret123')->assertStatus(429);

        $this->assertEquals(5, RateLimiter::attempts($this->userIpKey(self::A)));
        $this->assertEquals(4, RateLimiter::attempts($this->userKey()));
    }
}

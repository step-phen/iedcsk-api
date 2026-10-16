<?php

namespace Tests\Feature;

use App\Http\Requests\LoginRequest;
use App\Models\Teacher;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LoginDeviceCookieTest extends TestCase
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

    /** The encrypted cookie value exactly as the browser would send it back. */
    private function deviceCookie(string $ip, string $username, string $password = 'secret123'): string
    {
        return $this->loginFrom($ip, $username, $password)->assertOk()->getCookie(LoginRequest::DEVICE_COOKIE, false)->getValue();
    }

    /** @return array<string, mixed> */
    private function payload(TestResponse $response): array
    {
        return json_decode($response->getCookie(LoginRequest::DEVICE_COOKIE)->getValue(), true);
    }

    // An attacker fills the account-wide bucket from two other addresses.
    private function exhaust(string $username): void
    {
        foreach (['192.0.2.101', '192.0.2.102'] as $ip) {
            for ($i = 0; $i < 5; $i++) {
                $this->loginFrom($ip, $username)->assertStatus(422);
            }
        }
    }

    // D1
    public function test_cookie_is_encrypted_scoped_and_holds_no_username(): void
    {
        $response = $this->loginFrom(self::A, 'tina', 'secret123')->assertOk();
        $cookie = $response->getCookie(LoginRequest::DEVICE_COOKIE, false);

        $this->assertSame('/login', $cookie->getPath());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $cookie->getExpiresTime(), 5);

        $envelope = json_decode(base64_decode($cookie->getValue()), true);
        $this->assertArrayHasKey('mac', $envelope);
        $this->assertStringNotContainsString('"d"', base64_decode($cookie->getValue()));

        $payload = $this->payload($response);
        $this->assertSame(['d', 'i', 'p', 't'], collect($payload)->keys()->sort()->values()->all());
        $this->assertSame(40, strlen($payload['d']));
        $this->assertSame($this->tina->id, $payload['t']);
        $this->assertNotContains('tina', $payload);
    }

    // D2
    public function test_valid_cookie_lets_the_teacher_in_while_the_account_bucket_is_full(): void
    {
        $cookie = $this->deviceCookie(self::A, 'tina');
        $this->exhaust('tina');

        $this->loginFrom(self::D, 'tina', 'secret123')->assertStatus(429);
        $this->loginFrom(self::D, 'tina', 'secret123', $cookie)->assertOk();
    }

    // D3
    public function test_forged_or_tampered_cookie_is_ignored(): void
    {
        $response = $this->loginFrom(self::A, 'tina', 'secret123')->assertOk();
        $plain = json_encode($this->payload($response));
        $raw = $response->getCookie(LoginRequest::DEVICE_COOKIE, false)->getValue();
        $tampered = substr_replace($raw, $raw[20] === 'A' ? 'B' : 'A', 20, 1);
        $this->exhaust('tina');

        $this->loginFrom(self::D, 'tina', 'secret123', $plain)->assertStatus(429);
        $this->loginFrom(self::D, 'tina', 'secret123', $tampered)->assertStatus(429);
    }

    // D4
    public function test_another_teachers_cookie_is_ignored(): void
    {
        Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'secret123']);
        $omars = $this->deviceCookie(self::B, 'omar');
        $this->exhaust('tina');

        $this->loginFrom(self::D, 'tina', 'secret123', $omars)->assertStatus(429);
    }

    // D5
    public function test_password_change_revokes_the_cookie(): void
    {
        $cookie = $this->deviceCookie(self::A, 'tina');
        $this->actingAs($this->tina)->putJson('/api/me/password', ['currentPassword' => 'secret123', 'newPassword' => 'newpass123', 'confirmPassword' => 'newpass123'])
            ->assertNoContent();
        $this->exhaust('tina');

        $this->loginFrom(self::D, 'tina', 'newpass123', $cookie)->assertStatus(429);
    }

    // D6
    public function test_deactivation_revokes_the_cookie(): void
    {
        $cookie = $this->deviceCookie(self::A, 'tina');
        $this->tina->update(['is_active' => false]);
        $this->exhaust('tina');

        // 429 from the account bucket, not 422 from a device bucket.
        $this->loginFrom(self::D, 'tina', 'secret123', $cookie)->assertStatus(429);
    }

    // D6b
    public function test_handover_accept_revokes_both_teachers_cookies(): void
    {
        $tinas = $this->deviceCookie(self::A, 'tina');
        $this->actingAs($this->tina)->postJson('/api/me/handover', [
            'currentPassword' => 'secret123', 'teacherName' => 'Bea', 'username' => 'bea', 'password' => 'temp-pass1', 'confirmPassword' => 'temp-pass1',
        ])->assertCreated();
        $beasTemporary = $this->deviceCookie(self::B, 'bea', 'temp-pass1');

        $this->actingAs(Teacher::where('username', 'bea')->firstOrFail())
            ->postJson('/api/me/handover/accept', ['newPassword' => 'own-pass1', 'confirmPassword' => 'own-pass1'])->assertNoContent();
        $beasNew = $this->deviceCookie(self::C, 'bea', 'own-pass1');
        $this->exhaust('tina');
        $this->exhaust('bea');

        $this->loginFrom(self::D, 'tina', 'secret123', $tinas)->assertStatus(429);
        $this->loginFrom(self::D, 'bea', 'own-pass1', $beasTemporary)->assertStatus(429);
        $this->loginFrom(self::D, 'bea', 'own-pass1', $beasNew)->assertOk();
    }

    // D6c
    public function test_handover_cancel_revokes_the_incoming_teachers_cookie(): void
    {
        $this->actingAs($this->tina)->postJson('/api/me/handover', [
            'currentPassword' => 'secret123', 'teacherName' => 'Bea', 'username' => 'bea', 'password' => 'temp-pass1', 'confirmPassword' => 'temp-pass1',
        ])->assertCreated();
        $beas = $this->deviceCookie(self::B, 'bea', 'temp-pass1');

        $this->actingAs($this->tina->fresh())->postJson('/api/me/handover/cancel')->assertNoContent();
        $this->exhaust('bea');

        $this->loginFrom(self::D, 'bea', 'temp-pass1', $beas)->assertStatus(429);
    }

    // D7
    public function test_cookie_expires_on_the_server_after_30_days(): void
    {
        $cookie = $this->deviceCookie(self::A, 'tina');

        $this->travel(29)->days();
        $this->exhaust('tina');
        $this->loginFrom(self::D, 'tina', 'secret123', $cookie)->assertOk();

        $this->travel(2)->days();
        $this->exhaust('tina');
        $this->loginFrom(self::D, 'tina', 'secret123', $cookie)->assertStatus(429);
    }

    // D8
    public function test_failures_through_a_cookie_cannot_lock_the_account_bucket(): void
    {
        $cookie = $this->deviceCookie(self::A, 'tina');
        foreach ([self::A, self::B] as $ip) {
            for ($i = 0; $i < 5; $i++) {
                $this->loginFrom($ip, 'tina', 'wrong', $cookie)->assertStatus(422);
            }
        }

        $this->assertEquals(0, RateLimiter::attempts(LoginRequest::key('user', 'tina')));
        $this->loginFrom(self::C, 'tina', 'secret123', $cookie)->assertStatus(429);
        $this->loginFrom(self::D, 'tina', 'secret123')->assertOk();
    }

    // D9
    public function test_cookie_survives_a_username_change(): void
    {
        $cookie = $this->deviceCookie(self::A, 'tina');
        $this->actingAs($this->tina)->putJson('/api/me', ['teacherName' => 'Tina', 'username' => 'tinac'])->assertOk();
        $this->exhaust('tinac');

        $this->loginFrom(self::D, 'tinac', 'secret123')->assertStatus(429);
        $this->loginFrom(self::D, 'tinac', 'secret123', $cookie)->assertOk();
    }

    // D10
    public function test_device_id_is_kept_and_issue_time_refreshed(): void
    {
        $first = $this->loginFrom(self::A, 'tina', 'secret123')->assertOk();
        $this->travel(1)->minutes();
        $second = $this->loginFrom(self::A, 'tina', 'secret123', $first->getCookie(LoginRequest::DEVICE_COOKIE, false)->getValue())->assertOk();

        $this->assertSame($this->payload($first)['d'], $this->payload($second)['d']);
        $this->assertGreaterThan($this->payload($first)['i'], $this->payload($second)['i']);
    }

    // D11
    public function test_cookie_is_never_authentication(): void
    {
        $cookie = $this->deviceCookie(self::A, 'tina');
        $attempts = 0;
        Event::listen(Attempting::class, function () use (&$attempts) {
            $attempts++;
        });

        $this->loginFrom(self::D, 'tina', 'wrong', $cookie)->assertStatus(422);
        $this->assertSame(1, $attempts);
        $this->loginFrom(self::D, 'tina', '', $cookie)->assertStatus(422)->assertJsonValidationErrors('password');

        $this->app['auth']->forgetGuards();
        $this->app['session.store']->flush();
        $this->withCredentials()->withUnencryptedCookie(LoginRequest::DEVICE_COOKIE, $cookie)
            ->getJson('/api/me', ['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/'])
            ->assertUnauthorized();
    }
}

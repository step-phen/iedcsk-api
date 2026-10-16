<?php

namespace App\Http\Requests;

use App\Models\Teacher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    // Names the browser for rate limiting only; it never signs anyone in or skips the password check.
    public const DEVICE_COOKIE = 'login_device';

    private const DEVICE_TTL = 30 * 86400;

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /** Signs the teacher in; throws 422 on bad credentials and 429 when a per-username limit is full. */
    public function authenticate(): Teacher
    {
        // Usernames are stored lowercase, and deactivated teachers cannot sign in.
        $username = strtolower($this->validated('username'));
        $deviceId = $this->deviceId($username);
        $limits = self::limits($username, (string) $this->ip(), $deviceId);

        $this->reserve($limits, $username);

        $guard = Auth::guard('web');
        if (! $guard->attempt(['username' => $username, 'password' => $this->validated('password'), 'is_active' => true])) {
            Log::warning('Teacher login failed', [
                'ip' => $this->ip(),
                'teacher_id' => $guard->getLastAttempted()?->getAuthIdentifier(),
                'username_hash' => self::usernameHash($username),
            ]);

            throw ValidationException::withMessages(['username' => 'Invalid username or password.']);
        }

        // A success hands back only its own slots; nothing is cleared, so concurrent failures stay counted.
        foreach ($limits as $key => [, $window]) {
            if (RateLimiter::attempts($key) > 0) {
                RateLimiter::decrement($key, $window);
            }
        }

        /** @var Teacher $teacher */
        $teacher = $guard->user();
        Cookie::queue(self::DEVICE_COOKIE, json_encode([
            'd' => $deviceId ?? Str::random(40),
            't' => $teacher->id,
            'p' => self::passwordFingerprint($teacher),
            'i' => now()->getTimestamp(),
        ]), self::DEVICE_TTL / 60, '/login');

        return $teacher;
    }

    /** @return array<string, array{int, int}> rate-limit key => [max failed attempts, window seconds] */
    public static function limits(string $username, string $ip, ?string $deviceId = null): array
    {
        return [
            self::key('user-ip', $ip.'|'.$username) => [5, 60],
            ($deviceId ? self::key('device', $deviceId) : self::key('user', $username)) => [10, 900],
        ];
    }

    /** Cache key for one bucket; the HMAC keeps usernames, IPs and device ids out of the cache table. */
    public static function key(string $bucket, string $value): string
    {
        return "login:{$bucket}:".hash_hmac('sha256', $value, config('app.key'));
    }

    /** Short keyed hash that lets logs correlate attempts on one username without storing it. */
    public static function usernameHash(string $username): string
    {
        return substr(hash_hmac('sha256', $username, config('app.key')), 0, 16);
    }

    // Counts this attempt before the password check, and only the returned count decides, so parallel requests cannot all slip under a limit.
    private function reserve(array $limits, string $username): void
    {
        foreach ($limits as $key => [$max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $this->throttled($limits, $username);
            }
        }

        $taken = [];
        foreach ($limits as $key => [$max, $window]) {
            $taken[$key] = $window;
            if (RateLimiter::hit($key, $window) > $max) {
                foreach ($taken as $takenKey => $takenWindow) {
                    RateLimiter::decrement($takenKey, $takenWindow);
                }
                $this->throttled($limits, $username);
            }
        }
    }

    private function throttled(array $limits, string $username): never
    {
        $waits = [1];
        foreach ($limits as $key => [$max]) {
            if (RateLimiter::attempts($key) >= $max) {
                $waits[] = RateLimiter::availableIn($key);
            }
        }

        Log::warning('Teacher login throttled', ['ip' => $this->ip(), 'username_hash' => self::usernameHash($username)]);

        abort(429, 'Too Many Attempts.', ['Retry-After' => max($waits)]);
    }

    // The device id counts only while the cookie is fresh and still matches this active teacher's username and password.
    private function deviceId(string $username): ?string
    {
        $data = json_decode((string) $this->cookie(self::DEVICE_COOKIE), true);
        if (! is_array($data) || ! is_string($data['d'] ?? null) || ! is_int($data['t'] ?? null)
            || ! is_string($data['p'] ?? null) || ! is_int($data['i'] ?? null)) {
            return null;
        }

        if (now()->getTimestamp() - $data['i'] > self::DEVICE_TTL) {
            return null;
        }

        $teacher = Teacher::whereKey($data['t'])->where('is_active', true)->first();

        return $teacher && $teacher->username === $username && hash_equals(self::passwordFingerprint($teacher), $data['p'])
            ? $data['d']
            : null;
    }

    private static function passwordFingerprint(Teacher $teacher): string
    {
        return hash_hmac('sha256', $teacher->password_hash, config('app.key'));
    }
}

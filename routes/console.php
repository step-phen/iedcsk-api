<?php

use App\Http\Requests\LoginRequest;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Unlocks one teacher's login limits without touching any other cache entry.
Artisan::command('login:reset {username} {--ip= : Also clear this IP\'s per-username limit}', function (string $username) {
    $username = strtolower($username);
    $ip = $this->option('ip');

    RateLimiter::clear(LoginRequest::key('user', $username));
    if ($ip) {
        RateLimiter::clear(LoginRequest::key('user-ip', $ip.'|'.$username));
    }

    Log::info('Login rate limit reset', ['username_hash' => LoginRequest::usernameHash($username), 'ip' => $ip]);
    $this->info("Cleared login limits for {$username}.");
})->purpose('Clear the login rate limits for one teacher');

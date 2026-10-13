<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_local_nginx_forwarded_https_builds_https_links(): void
    {
        Route::get('/_proxy-check', fn () => asset('storage/x.mp4'));

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/_proxy-check')
            ->assertSeeText('https://');
    }

    public function test_outside_clients_cannot_fake_https(): void
    {
        Route::get('/_proxy-check', fn () => asset('storage/x.mp4'));

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/_proxy-check')
            ->assertSeeText('http://');
    }
}

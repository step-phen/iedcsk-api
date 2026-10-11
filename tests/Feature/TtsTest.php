<?php

namespace Tests\Feature;

use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TtsTest extends TestCase
{
    use RefreshDatabase;

    public function test_speech_is_made_once_then_served_from_storage(): void
    {
        Storage::fake('public');
        Http::fake(['api.elevenlabs.io/*' => Http::response('mp3-bytes', 200)]);
        $this->withHeader('Referer', 'http://localhost:3000/');

        $this->postJson('/api/tts', ['text' => 'Hello'])->assertUnauthorized();

        $this->actingAs(Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']));
        $this->postJson('/api/tts', ['text' => str_repeat('a', 301)])->assertStatus(422);
        $url = $this->postJson('/api/tts', ['text' => 'Hello'])->assertOk()->json('url');
        $this->assertSame($url, $this->postJson('/api/tts', ['text' => 'Hello'])->json('url'));

        Http::assertSentCount(1);
        Storage::disk('public')->assertExists(substr($url, strlen('/storage/')));
    }

    public function test_an_elevenlabs_failure_is_a_502(): void
    {
        Storage::fake('public');
        Http::fake(['api.elevenlabs.io/*' => Http::response('bad key', 401)]);
        $this->actingAs(Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'x']));

        $this->postJson('/api/tts', ['text' => 'Hello'])->assertStatus(502);
    }
}

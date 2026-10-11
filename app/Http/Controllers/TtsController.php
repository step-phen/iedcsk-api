<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// Game speech: ElevenLabs makes each line once, then every device plays the saved mp3.
class TtsController extends Controller
{
    private const MODEL = 'eleven_turbo_v2_5';

    // Slowed down for kindergarten listeners; part of the file name so changing it makes new clips.
    private const SPEED = 0.85;

    public function __invoke(Request $request): JsonResponse
    {
        $learner = $request->hasSession() && $request->session()->has('learner');
        if (! $learner && ! Auth::guard('web')->check()) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }
        $text = $request->validate(['text' => ['required', 'string', 'max:300']])['text'];

        $voice = config('services.elevenlabs.voice_id');
        $file = 'tts/'.hash('sha256', $voice.':'.self::MODEL.':'.self::SPEED.':'.$text).'.mp3';

        if (! Storage::disk('public')->exists($file)) {
            $response = Http::withHeaders(['xi-api-key' => config('services.elevenlabs.key')])
                ->accept('audio/mpeg')
                ->timeout(20)
                ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voice}", [
                    'text' => $text,
                    'model_id' => self::MODEL,
                    'voice_settings' => ['stability' => 0.5, 'similarity_boost' => 0.75, 'speed' => self::SPEED],
                ]);
            if ($response->failed()) {
                report(new \RuntimeException('ElevenLabs '.$response->status().': '.substr($response->body(), 0, 200)));

                return response()->json(['message' => 'Speech is unavailable right now.'], 502);
            }
            Storage::disk('public')->put($file, $response->body());
        }

        // Relative, so the Next /storage rewrite serves it on any LAN IP.
        return response()->json(['url' => '/storage/'.$file]);
    }
}

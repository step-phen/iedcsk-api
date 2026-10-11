<?php

use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'username' => ['required', 'string'],
        'password' => ['required', 'string'],
    ]);

    // Usernames are stored lowercase, and deactivated teachers cannot sign in.
    if (! Auth::attempt(['username' => strtolower($credentials['username']), 'password' => $credentials['password'], 'is_active' => true])) {
        throw ValidationException::withMessages(['username' => 'Invalid username or password.']);
    }

    // New session id on login blocks session fixation.
    $request->session()->regenerate();

    /** @var Teacher $teacher */
    $teacher = Auth::user();

    return $teacher->toMeArray();
})->middleware('throttle:10,1');

Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->noContent();
});

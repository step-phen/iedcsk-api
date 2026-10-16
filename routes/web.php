<?php

use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/login', function (LoginRequest $request) {
    $teacher = $request->authenticate();

    // New session id on login blocks session fixation.
    $request->session()->regenerate();

    return $teacher->toMeArray();
})->middleware('throttle:10,1');

Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->noContent();
});

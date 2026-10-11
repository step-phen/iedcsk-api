<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Models\Teacher;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AccountController extends Controller
{
    public function update(UpdateAccountRequest $request, #[CurrentUser] Teacher $teacher): JsonResponse
    {
        $data = $request->validated();
        $teacher->update(['name' => $data['teacherName'], 'username' => $data['username']]);

        return response()->json($teacher->toMeArray());
    }

    public function updatePassword(UpdatePasswordRequest $request, #[CurrentUser] Teacher $teacher): Response
    {
        // The model's hashed cast hashes it on save.
        $teacher->update(['password_hash' => $request->validated('newPassword')]);

        return response()->noContent();
    }
}

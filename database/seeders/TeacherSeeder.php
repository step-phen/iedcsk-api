<?php

namespace Database\Seeders;

use App\Models\Teacher;
use Illuminate\Database\Seeder;
use RuntimeException;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        // Credentials come from .env so no password is committed to the repo.
        $username = env('SEED_TEACHER_USERNAME');
        $password = env('SEED_TEACHER_PASSWORD');
        $name = env('SEED_TEACHER_NAME');

        if (! $username || ! $password || ! $name) {
            throw new RuntimeException('Set SEED_TEACHER_USERNAME, SEED_TEACHER_PASSWORD and SEED_TEACHER_NAME in .env before seeding.');
        }

        // Re-running updates the same account instead of failing on the unique username.
        Teacher::updateOrCreate(
            ['username' => strtolower($username)],
            ['name' => $name, 'password_hash' => $password],
        );
    }
}

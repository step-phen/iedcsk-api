<?php

namespace Tests\Feature;

use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherAuthTest extends TestCase
{
    use RefreshDatabase;

    private const SPA = ['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/'];

    public function test_login_me_logout_flow(): void
    {
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'secret123']);

        $this->withHeaders(self::SPA)->getJson('/api/me')->assertUnauthorized();

        $this->withHeaders(self::SPA)->postJson('/login', ['username' => 'tina', 'password' => 'wrong'])->assertStatus(422);

        // Usernames are matched case-insensitively.
        $this->withHeaders(self::SPA)->postJson('/login', ['username' => 'TINA', 'password' => 'secret123'])
            ->assertOk()
            ->assertExactJson(['teacherId' => $tina->id, 'username' => 'tina', 'teacherName' => 'Tina', 'handoverPending' => false]);

        $this->withHeaders(self::SPA)->getJson('/api/me')->assertOk()->assertJsonPath('username', 'tina');

        $this->withHeaders(self::SPA)->postJson('/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_teacher_updates_account_details_and_password(): void
    {
        $tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'secret123']);
        Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'secret123']);

        $this->putJson('/api/me', ['teacherName' => 'X', 'username' => 'x'])->assertUnauthorized();

        // Username is stored lowercase and must stay unique.
        $this->actingAs($tina)->putJson('/api/me', ['teacherName' => 'Tina Cruz', 'username' => 'TinaC'])
            ->assertOk()->assertJson(['teacherName' => 'Tina Cruz', 'username' => 'tinac']);
        $this->actingAs($tina)->putJson('/api/me', ['teacherName' => 'Tina', 'username' => 'omar'])->assertJsonValidationErrors('username');
        $this->actingAs($tina)->putJson('/api/me', ['teacherName' => '', 'username' => 'tina c'])->assertJsonValidationErrors(['teacherName', 'username']);

        $this->actingAs($tina)->putJson('/api/me/password', ['currentPassword' => 'wrong', 'newPassword' => 'newpass123', 'confirmPassword' => 'newpass123'])
            ->assertJsonValidationErrors('currentPassword');
        $this->actingAs($tina)->putJson('/api/me/password', ['currentPassword' => 'secret123', 'newPassword' => 'short', 'confirmPassword' => 'other'])
            ->assertJsonValidationErrors(['newPassword', 'confirmPassword']);
        $this->actingAs($tina)->putJson('/api/me/password', ['currentPassword' => 'secret123', 'newPassword' => 'newpass123', 'confirmPassword' => 'newpass123'])
            ->assertNoContent();

        $this->assertTrue(Hash::check('newpass123', $tina->fresh()->password_hash));
    }

    public function test_inactive_teacher_cannot_log_in(): void
    {
        Teacher::create(['username' => 'omar', 'name' => 'Omar', 'password_hash' => 'secret123', 'is_active' => false]);

        $this->withHeaders(self::SPA)->postJson('/login', ['username' => 'omar', 'password' => 'secret123'])->assertStatus(422);
    }
}

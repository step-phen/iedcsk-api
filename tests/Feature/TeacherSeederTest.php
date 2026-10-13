<?php

namespace Tests\Feature;

use App\Models\Teacher;
use Database\Seeders\TeacherSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class TeacherSeederTest extends TestCase
{
    use RefreshDatabase;

    // Runs TeacherSeeder with the given SEED_TEACHER_* values, then puts the real environment back.
    private function seedTeacher(string $username = 'Tina', string $password = 'first-pass1', string $name = 'Tina Cruz'): void
    {
        $server = $_SERVER;
        try {
            $_SERVER = ['SEED_TEACHER_USERNAME' => $username, 'SEED_TEACHER_PASSWORD' => $password, 'SEED_TEACHER_NAME' => $name] + $_SERVER;
            $this->seed(TeacherSeeder::class);
        } finally {
            $_SERVER = $server;
        }
    }

    /** @return array<int, array{string, string, string, bool}> every teacher's identity, password hash and status */
    private function accounts(): array
    {
        return Teacher::orderBy('id')->get()->map(fn (Teacher $t) => [$t->username, $t->name, $t->password_hash, $t->is_active])->all();
    }

    public function test_initial_seeding_creates_the_first_teacher(): void
    {
        $this->seedTeacher();

        $tina = Teacher::sole();
        $this->assertSame(['tina', 'Tina Cruz', true], [$tina->username, $tina->name, $tina->is_active]);
        $this->assertTrue(Hash::check('first-pass1', $tina->password_hash));
    }

    public function test_initial_seeding_requires_the_seed_settings(): void
    {
        $this->expectException(RuntimeException::class);
        $this->seedTeacher(password: '');
    }

    public function test_repeated_seeding_keeps_a_changed_password_and_adds_no_account(): void
    {
        $this->seedTeacher();
        Teacher::sole()->update(['password_hash' => 'changed-in-app1', 'name' => 'Tina M. Cruz']);
        $before = $this->accounts();

        // Same settings, different settings, and no settings at all leave the account alone.
        $this->seedTeacher();
        $this->seedTeacher('Tina', 'other-pass1', 'Someone Else');
        $this->seedTeacher('newbie', 'newbie-pass1', 'Newbie');
        $this->seedTeacher('', '', '');

        $this->assertSame($before, $this->accounts());
        $this->assertTrue(Hash::check('changed-in-app1', Teacher::sole()->password_hash));
    }

    public function test_seeding_after_a_handover_keeps_both_accounts_as_they_are(): void
    {
        $this->seedTeacher();
        $tina = Teacher::sole();
        $this->actingAs($tina)->postJson('/api/me/handover', [
            'currentPassword' => 'first-pass1', 'teacherName' => 'Bea', 'username' => 'bea', 'password' => 'temp-pass1', 'confirmPassword' => 'temp-pass1',
        ])->assertCreated();
        $this->actingAs(Teacher::where('username', 'bea')->firstOrFail())
            ->postJson('/api/me/handover/accept', ['newPassword' => 'own-pass1', 'confirmPassword' => 'own-pass1'])->assertNoContent();
        $before = $this->accounts();
        $handovers = DB::table('teacher_handovers')->get();

        $this->seedTeacher();

        // The retired teacher stays retired and the new one keeps the password they chose.
        $this->assertSame($before, $this->accounts());
        $this->assertSame([false, true], array_column($before, 3));
        $this->assertTrue(Hash::check('own-pass1', Teacher::where('username', 'bea')->value('password_hash')));
        $this->assertEquals($handovers, DB::table('teacher_handovers')->get());
    }
}

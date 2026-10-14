<?php

namespace Tests\Feature;

use App\Models\SchoolYear;
use App\Models\Teacher;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class HandoverTest extends TestCase
{
    use RefreshDatabase;

    private const SPA = ['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/'];

    private Teacher $tina;

    private int $joy;

    private int $old;

    private int $attendanceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CurriculumSeeder::class);
        SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'status' => 'active']);
        $past = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-06-01', 'end_date' => '2026-03-31', 'status' => 'completed']);
        $this->tina = Teacher::create(['username' => 'tina', 'name' => 'Tina', 'password_hash' => 'secret123']);
        $this->joy = $this->setUpSections($this->tina, ['Joy'])['Joy'];

        // Last year's class with one attendance mark Tina recorded.
        $this->old = DB::table('sections')->insertGetId(['school_year_id' => $past->id, 'teacher_id' => $this->tina->id, 'name' => 'Hope', 'grade_level' => 'Kindergarten']);
        $ana = DB::table('learners')->insertGetId(['first_name' => 'Ana', 'last_name' => 'Cruz', 'birth_date' => '2021-01-01']);
        $enrollment = DB::table('learner_school_years')->insertGetId(['learner_id' => $ana, 'school_year_id' => $past->id, 'section_id' => $this->old, 'enrolled_on' => '2025-06-01', 'status' => 'completed']);
        $this->attendanceId = DB::table('attendance')->insertGetId(['enrollment_id' => $enrollment, 'attendance_date' => '2025-07-01', 'status' => 'present', 'recorded_by' => $this->tina->id, 'recorded_at' => now()]);
    }

    private function start(Teacher $from, string $username, array $overrides = [])
    {
        return $this->actingAs($from->fresh())->postJson('/api/me/handover', $overrides + [
            'currentPassword' => 'secret123', 'teacherName' => ucfirst($username), 'username' => $username, 'password' => 'temp-pass1', 'confirmPassword' => 'temp-pass1',
        ]);
    }

    private function accept(string $username, string $password = 'own-pass1')
    {
        return $this->actingAs(Teacher::where('username', $username)->firstOrFail())
            ->postJson('/api/me/handover/accept', ['newPassword' => $password, 'confirmPassword' => $password]);
    }

    // A real browser login; the test app keeps the sanctum guard from earlier requests, so switch back first.
    private function login(string $username, string $password)
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return $this->withHeaders(self::SPA)->postJson('/login', ['username' => $username, 'password' => $password]);
    }

    private function owner(int $section): int
    {
        return DB::table('sections')->where('id', $section)->value('teacher_id');
    }

    public function test_start_is_validated_and_moves_nothing_until_accepted(): void
    {
        $this->start($this->tina, 'bea', ['currentPassword' => 'wrong'])->assertJsonValidationErrors('currentPassword');
        $this->start($this->tina, 'tina')->assertJsonValidationErrors('username');
        $this->start($this->tina, 'bea', ['confirmPassword' => 'other'])->assertJsonValidationErrors('confirmPassword');

        $this->start($this->tina, 'BEA')->assertCreated();
        $this->start($this->tina, 'cid')->assertJsonValidationErrors('username');
        $this->actingAs($this->tina->fresh())->getJson('/api/me/handover')->assertExactJson([
            'outgoing' => ['toName' => 'BEA', 'toUsername' => 'bea', 'createdAt' => DB::table('teacher_handovers')->value('created_at')],
            'incoming' => null,
        ]);

        // Tina keeps working; Bea can sign in but sees only the handover.
        $this->assertSame($this->tina->id, $this->owner($this->joy));
        $this->actingAs($this->tina->fresh())->getJson('/api/sections')->assertOk()->assertJsonCount(1);
        $this->login('bea', 'temp-pass1')->assertOk()->assertJsonPath('handoverPending', true);
        $bea = Teacher::where('username', 'bea')->first();
        $this->actingAs($bea)->getJson('/api/me')->assertOk()->assertJsonPath('handoverPending', true);
        $this->actingAs($bea)->getJson('/api/me/handover')->assertJsonPath('incoming', ['fromName' => 'Tina', 'sectionCount' => 2]);
        $this->actingAs($bea)->getJson('/api/sections')->assertForbidden()->assertJsonPath('code', 'handover_pending');
        $this->actingAs($bea)->getJson('/api/notifications')->assertForbidden();
        $this->actingAs($bea)->putJson('/api/me', ['teacherName' => 'B', 'username' => 'bee'])->assertForbidden();
        $this->actingAs($bea)->putJson('/api/me/password', ['currentPassword' => 'temp-pass1', 'newPassword' => 'own-pass1', 'confirmPassword' => 'own-pass1'])->assertForbidden();
    }

    public function test_accept_moves_every_section_keeps_history_and_retires_the_outgoing_teacher(): void
    {
        $this->start($this->tina, 'bea')->assertCreated();
        // A browser Tina is still signed in on.
        DB::table('sessions')->insert(['id' => 'tina-tab', 'user_id' => $this->tina->id, 'payload' => '', 'last_activity' => time()]);

        $this->accept('bea', 'temp-pass1')->assertJsonValidationErrors('newPassword');
        $this->accept('bea')->assertNoContent();
        // Finalised once: a repeated accept finds nothing waiting.
        $this->accept('bea', 'own-pass2')->assertStatus(409);

        $bea = Teacher::where('username', 'bea')->first();
        $this->assertSame([$bea->id, $bea->id], [$this->owner($this->joy), $this->owner($this->old)]);
        $this->assertSame([$bea->id], DB::table('section_modules')->where('section_id', $this->joy)->distinct()->pluck('teacher_id')->all());
        $this->assertTrue(Hash::check('own-pass1', $bea->password_hash));
        $this->assertSame($this->tina->id, DB::table('attendance')->where('id', $this->attendanceId)->value('recorded_by'));
        $this->assertFalse($this->tina->fresh()->is_active);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->tina->id)->count());

        $audit = DB::table('teacher_handovers')->first();
        $this->assertSame('accepted', $audit->status);
        $this->assertEqualsCanonicalizing([$this->joy, $this->old], json_decode($audit->section_ids));
        $this->assertNotNull($audit->accepted_at);

        // Bea works normally, last year's class included; Tina is locked out at once and at login.
        $this->actingAs($bea)->getJson('/api/me')->assertJsonPath('handoverPending', false);
        $this->actingAs($bea)->getJson('/api/sections')->assertOk()->assertJsonPath('0.sectionId', $this->joy);
        $this->actingAs($bea)->getJson("/api/sections/{$this->old}/schedule")->assertOk();
        $this->actingAs($this->tina->fresh())->getJson('/api/sections')->assertUnauthorized();
        $this->login('tina', 'secret123')->assertStatus(422);
    }

    public function test_accept_waits_for_the_outgoing_class_to_end(): void
    {
        $this->start($this->tina, 'bea')->assertCreated();
        DB::table('classroom_sessions')->insert([
            'section_module_id' => DB::table('section_modules')->where('section_id', $this->joy)->value('id'),
            'quarter_id' => DB::table('quarters')->insertGetId(['school_year_id' => DB::table('school_years')->where('status', 'active')->value('id'), 'quarter_number' => 1, 'name' => '1st Quarter', 'start_date' => '2026-06-01', 'end_date' => '2026-12-31', 'status' => 'active']),
            'teacher_id' => $this->tina->id, 'session_date' => '2026-10-10', 'planned_start_time' => '08:00', 'planned_end_time' => '09:00', 'started_at' => now(), 'status' => 'ongoing',
        ]);

        $this->accept('bea')->assertJsonValidationErrors(['newPassword' => 'Ask Tina to end their running class first.']);
        $this->assertSame($this->tina->id, $this->owner($this->joy));
        $this->assertSame('pending', DB::table('teacher_handovers')->value('status'));
    }

    public function test_a_failure_mid_accept_rolls_everything_back(): void
    {
        $this->start($this->tina, 'bea')->assertCreated();
        Teacher::updating(function (Teacher $teacher) {
            if ($teacher->isDirty('is_active') && ! $teacher->is_active) {
                throw new RuntimeException('disk full');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->accept('bea');
            $this->fail('Accept should have thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('disk full', $e->getMessage());
        }

        $this->assertSame([$this->tina->id, $this->tina->id], [$this->owner($this->joy), $this->owner($this->old)]);
        $this->assertSame([$this->tina->id], DB::table('section_modules')->where('section_id', $this->joy)->distinct()->pluck('teacher_id')->all());
        $this->assertTrue(Hash::check('temp-pass1', Teacher::where('username', 'bea')->value('password_hash')));
        $this->assertTrue($this->tina->fresh()->is_active);
        $this->assertSame('pending', DB::table('teacher_handovers')->value('status'));
    }

    public function test_cancel_retires_the_unused_account_and_cannot_race_an_accept(): void
    {
        $this->start($this->tina, 'bea')->assertCreated();
        $bea = Teacher::where('username', 'bea')->first();
        DB::table('sessions')->insert(['id' => 'bea-tab', 'user_id' => $bea->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($bea)->postJson('/api/me/handover/cancel')->assertStatus(409);
        $this->actingAs($this->tina->fresh())->postJson('/api/me/handover/cancel')->assertNoContent();
        $this->actingAs($this->tina->fresh())->postJson('/api/me/handover/cancel')->assertStatus(409);

        $this->assertSame('cancelled', DB::table('teacher_handovers')->value('status'));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $bea->id)->count());
        // Finalised once: the cancelled handover can no longer be accepted.
        $this->actingAs($bea->fresh())->postJson('/api/me/handover/accept', ['newPassword' => 'own-pass1', 'confirmPassword' => 'own-pass1'])->assertUnauthorized();
        $this->assertSame($this->tina->id, $this->owner($this->joy));
        $this->login('bea', 'temp-pass1')->assertStatus(422);

        // Tina may start over with someone else.
        $this->start($this->tina, 'cid')->assertCreated();
    }

    public function test_repeated_handovers_pass_the_class_along(): void
    {
        $this->start($this->tina, 'bea')->assertCreated();
        $this->accept('bea')->assertNoContent();
        Teacher::where('username', 'bea')->first()->update(['password_hash' => 'secret123']);
        $this->start(Teacher::where('username', 'bea')->first(), 'cid')->assertCreated();
        $this->accept('cid')->assertNoContent();

        $cid = Teacher::where('username', 'cid')->first();
        $this->assertSame([$cid->id, $cid->id], [$this->owner($this->joy), $this->owner($this->old)]);
        $this->assertSame(['accepted', 'accepted'], DB::table('teacher_handovers')->orderBy('id')->pluck('status')->all());
        $this->assertSame([false, false], [$this->tina->fresh()->is_active, Teacher::where('username', 'bea')->first()->is_active]);
        $this->assertSame($this->tina->id, DB::table('attendance')->where('id', $this->attendanceId)->value('recorded_by'));
    }
}

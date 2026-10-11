<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptHandoverRequest;
use App\Http\Requests\StartHandoverRequest;
use App\Models\Teacher;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HandoverController extends Controller
{
    // The pending handover this teacher started or is waiting to accept, if any.
    public function show(#[CurrentUser] Teacher $teacher): JsonResponse
    {
        $pending = fn (string $side) => DB::table('teacher_handovers')->where([$side => $teacher->id, 'status' => 'pending'])->first();
        $outgoing = $pending('from_teacher_id');
        $incoming = $pending('to_teacher_id');
        $to = $outgoing ? Teacher::find($outgoing->to_teacher_id) : null;
        $from = $incoming ? Teacher::find($incoming->from_teacher_id) : null;

        return response()->json([
            'outgoing' => $to ? ['toName' => $to->name, 'toUsername' => $to->username, 'createdAt' => $outgoing->created_at] : null,
            'incoming' => $from ? ['fromName' => $from->name, 'sectionCount' => $from->sections()->count()] : null,
        ]);
    }

    // Creates the incoming teacher's account; nothing moves until they sign in and accept.
    public function store(StartHandoverRequest $request, #[CurrentUser] Teacher $teacher): JsonResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($teacher, $data) {
            // The model's hashed cast hashes the temporary password on save.
            $incoming = Teacher::create(['name' => $data['teacherName'], 'username' => $data['username'], 'password_hash' => $data['password']]);
            DB::table('teacher_handovers')->insert(['from_teacher_id' => $teacher->id, 'to_teacher_id' => $incoming->id, 'status' => 'pending']);
        });

        return response()->json(['success' => true], 201);
    }

    // Moves every section of the outgoing teacher, then retires their account, all or nothing.
    public function accept(AcceptHandoverRequest $request, #[CurrentUser] Teacher $teacher): Response
    {
        DB::transaction(function () use ($request, $teacher) {
            // The lock serialises a racing accept or cancel; the status is checked again under it.
            $handover = DB::table('teacher_handovers')->where(['to_teacher_id' => $teacher->id, 'status' => 'pending'])->lockForUpdate()->first();
            if (! $handover) {
                abort(409, 'This handover is no longer waiting.');
            }
            $from = Teacher::query()->lockForUpdate()->findOrFail($handover->from_teacher_id);
            $classOpen = DB::table('classroom_sessions')->where('teacher_id', $from->id)->whereIn('status', ['scheduled', 'ongoing'])->exists();
            if ($classOpen) {
                throw ValidationException::withMessages(['newPassword' => "Ask {$from->name} to end their running class first."]);
            }

            $now = now();
            $sectionIds = $from->sections()->pluck('id')->all();
            DB::table('sections')->whereIn('id', $sectionIds)->update(['teacher_id' => $teacher->id]);
            // A section's module links belong to whoever owns the section.
            DB::table('section_modules')->whereIn('section_id', $sectionIds)->update(['teacher_id' => $teacher->id]);
            $teacher->update(['password_hash' => $request->validated('newPassword')]);
            $from->update(['is_active' => false]);
            // Ends every signed-in browser of the outgoing teacher; the middleware covers any request already in flight.
            DB::table('sessions')->where('user_id', $from->id)->delete();
            DB::table('teacher_handovers')->where('id', $handover->id)
                ->update(['status' => 'accepted', 'section_ids' => json_encode($sectionIds), 'accepted_at' => $now]);
        });

        return response()->noContent();
    }

    // The outgoing teacher withdraws; the unused incoming account is retired and kept for the audit trail.
    public function cancel(#[CurrentUser] Teacher $teacher): Response
    {
        DB::transaction(function () use ($teacher) {
            $handover = DB::table('teacher_handovers')->where(['from_teacher_id' => $teacher->id, 'status' => 'pending'])->lockForUpdate()->first();
            if (! $handover) {
                abort(409, 'This handover is no longer waiting.');
            }
            // ponytail: a cancelled account keeps its username taken; reuse needs a reactivate flow.
            Teacher::whereKey($handover->to_teacher_id)->update(['is_active' => false]);
            DB::table('sessions')->where('user_id', $handover->to_teacher_id)->delete();
            DB::table('teacher_handovers')->where('id', $handover->id)->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        });

        return response()->noContent();
    }
}

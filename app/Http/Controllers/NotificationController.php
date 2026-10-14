<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    // The teacher's latest learner finishes, newest first, for the header bell.
    public function index(#[CurrentUser] Teacher $teacher): JsonResponse
    {
        $mine = fn () => DB::table('notifications')->where('notifications.teacher_id', $teacher->id);

        return response()->json([
            'unread' => $mine()->whereNull('read_at')->count(),
            // The learner's section lets a click open the Classroom on the right class.
            'items' => $mine()
                ->join('learner_activity_assignments', 'learner_activity_assignments.id', '=', 'notifications.assignment_id')
                ->join('learner_school_years', 'learner_school_years.id', '=', 'learner_activity_assignments.enrollment_id')
                ->orderByDesc('notifications.id')->limit(30)
                ->get(['notifications.*', 'learner_school_years.section_id'])->map(fn ($n) => [
                    'id' => $n->id,
                    'sectionId' => $n->section_id,
                    'kind' => $n->kind,
                    'learnerName' => $n->learner_name,
                    'activityName' => $n->activity_name,
                    'read' => $n->read_at !== null,
                    'createdAt' => $n->created_at,
                ]),
        ]);
    }

    public function markRead(#[CurrentUser] Teacher $teacher): JsonResponse
    {
        DB::table('notifications')->where('teacher_id', $teacher->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}

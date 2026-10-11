<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeacherCanWork
{

    // Deactivated teachers lose access at once; an incoming teacher sees only the handover until they accept it.
    public function handle(Request $request, Closure $next): Response
    {
        $teacher = $request->user();
        // Strict false: a model built in memory has no is_active until the DB default is read back.
        if ($teacher && $teacher->is_active === false) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json(['message' => 'This account has been deactivated.'], 401);
        }

        $pending = $teacher && DB::table('teacher_handovers')->where(['to_teacher_id' => $teacher->id, 'status' => 'pending'])->exists();
        // Before accepting, only reading who they are and the handover routes themselves are allowed.
        $allowed = ($request->isMethod('GET') && $request->is('api/me')) || $request->is('api/me/handover', 'api/me/handover/*');
        if ($pending && ! $allowed) {
            return response()->json(['message' => 'Accept the class handover first.', 'code' => 'handover_pending'], 403);
        }

        return $next($request);
    }
}

<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\ClassroomRecordController;
use App\Http\Controllers\CurriculumController;
use App\Http\Controllers\HandoverController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\LearnerSessionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProgressReportController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\QuarterController;
use App\Http\Controllers\SchoolYearController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\TtsController;
use App\Http\Middleware\EnsureTeacherCanWork;
use App\Models\Province;
use App\Models\Teacher;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', EnsureTeacherCanWork::class])->group(function () {
    Route::get('/me', fn (#[CurrentUser] Teacher $teacher) => $teacher->toMeArray());
    Route::put('/me', [AccountController::class, 'update']);
    Route::put('/me/password', [AccountController::class, 'updatePassword']);
    Route::get('/me/handover', [HandoverController::class, 'show']);
    Route::post('/me/handover', [HandoverController::class, 'store']);
    Route::post('/me/handover/accept', [HandoverController::class, 'accept']);
    Route::post('/me/handover/cancel', [HandoverController::class, 'cancel']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read', [NotificationController::class, 'markRead']);

    Route::get('/school-years', [SchoolYearController::class, 'index']);
    Route::get('/school-years/active', [SchoolYearController::class, 'active']);
    Route::post('/school-years', [SchoolYearController::class, 'store']);
    Route::patch('/school-years/{schoolYear}', [SchoolYearController::class, 'update'])->whereNumber('schoolYear');
    Route::patch('/school-years/{schoolYear}/activate', [SchoolYearController::class, 'activate'])->whereNumber('schoolYear');
    Route::get('/school-years/{schoolYear}/quarters', [QuarterController::class, 'index'])->whereNumber('schoolYear');
    Route::put('/school-years/{schoolYear}/quarters', [QuarterController::class, 'saveAll'])->whereNumber('schoolYear');
    Route::put('/school-years/{schoolYear}/quarters/{number}', [QuarterController::class, 'save'])->whereNumber('schoolYear')->where('number', '[1-4]');
    Route::patch('/school-years/{schoolYear}/quarters/{number}/activate', [QuarterController::class, 'activate'])->whereNumber('schoolYear')->where('number', '[1-4]');

    Route::get('/sections', [SectionController::class, 'index']);
    Route::patch('/sections/{section}', [SectionController::class, 'update'])->whereNumber('section');
    Route::get('/sections/{section}/checklist', [CurriculumController::class, 'sectionChecklist'])->whereNumber('section');
    Route::put('/sections/{section}/checklist/ratings', [CurriculumController::class, 'saveChecklistRating'])->whereNumber('section');
    Route::get('/sections/{section}/active-session', [SectionController::class, 'activeSession'])->whereNumber('section');
    Route::get('/sections/{section}/curriculum-resources', [SectionController::class, 'curriculumResources'])->whereNumber('section');
    Route::get('/sections/{section}/overall-progress', [SectionController::class, 'overallProgress'])->whereNumber('section');
    Route::get('/sections/{section}/todays-schedule', [SectionController::class, 'todaysSchedule'])->whereNumber('section');
    Route::get('/sections/{section}/schedule', [SectionController::class, 'schedule'])->whereNumber('section');
    Route::put('/sections/{section}/schedule', [SectionController::class, 'updateSchedule'])->whereNumber('section');
    Route::get('/sections/{section}/classroom', [ClassroomController::class, 'show'])->whereNumber('section');
    Route::post('/sections/{section}/classroom/start', [ClassroomController::class, 'start'])->whereNumber('section');
    Route::post('/sections/{section}/classroom/end', [ClassroomController::class, 'end'])->whereNumber('section');
    Route::put('/sections/{section}/classroom/practical-rating', [ClassroomController::class, 'practicalRating'])->whereNumber('section');
    Route::put('/sections/{section}/attendance', [ClassroomController::class, 'attendance'])->whereNumber('section');
    Route::get('/sections/{section}/classroom-record', [ClassroomRecordController::class, 'show'])->whereNumber('section');
    Route::get('/sections/{section}/classroom-record/strengths', [ClassroomRecordController::class, 'strengths'])->whereNumber('section');
    Route::get('/sections/{section}/progress-reports', [ProgressReportController::class, 'index'])->whereNumber('section');
    Route::post('/sections/{section}/progress-reports', [ProgressReportController::class, 'store'])->whereNumber('section');
    Route::get('/progress-reports/{report}', [ProgressReportController::class, 'show'])->whereNumber('report');
    Route::put('/progress-reports/{report}', [ProgressReportController::class, 'update'])->whereNumber('report');
    Route::post('/progress-reports/{report}/regenerate', [ProgressReportController::class, 'regenerate'])->whereNumber('report');
    Route::post('/progress-reports/{report}/finalize', [ProgressReportController::class, 'finalize'])->whereNumber('report');
    Route::post('/progress-reports/{report}/reopen', [ProgressReportController::class, 'reopen'])->whereNumber('report');
    Route::get('/progress-reports/{report}/versions/{version}', [ProgressReportController::class, 'version'])->whereNumber(['report', 'version']);

    Route::get('/provinces', fn () => Province::orderBy('province_name')->get()
        ->map(fn (Province $p) => ['provinceId' => $p->id, 'provinceName' => $p->province_name]));

    Route::get('/domains', [CurriculumController::class, 'domains']);
    Route::post('/checklist-items', [CurriculumController::class, 'storeChecklistItem']);
    Route::put('/checklist-items/{item}', [CurriculumController::class, 'updateChecklistItem'])->whereNumber('item');
    Route::get('/modules', [CurriculumController::class, 'modules']);
    Route::post('/modules', [CurriculumController::class, 'storeModule']);
    Route::get('/modules/{module}/lessons', [CurriculumController::class, 'weekLessons'])->whereNumber('module');
    Route::post('/modules/{module}/lessons', [CurriculumController::class, 'storeLesson'])->whereNumber('module');
    Route::patch('/lessons/{lesson}', [CurriculumController::class, 'updateLesson'])->whereNumber('lesson');
    Route::post('/lessons/{lesson}/video', [CurriculumController::class, 'storeLessonVideo'])->whereNumber('lesson');
    Route::delete('/lessons/{lesson}/video', [CurriculumController::class, 'destroyLessonVideo'])->whereNumber('lesson');
    Route::post('/lessons/{lesson}/practical-activities', [CurriculumController::class, 'storePracticalActivity'])->whereNumber('lesson');
    // POST, not PUT: PHP only reads multipart uploads on POST, and an edit may carry a new video.
    Route::post('/practical-activities/{activity}', [CurriculumController::class, 'updatePracticalActivity'])->whereNumber('activity');
    Route::delete('/practical-activities/{activity}', [CurriculumController::class, 'destroyPracticalActivity'])->whereNumber('activity');
    Route::post('/practical-activities/{activity}/video', [CurriculumController::class, 'storePracticalActivityVideo'])->whereNumber('activity');

    Route::get('/learners', [LearnerController::class, 'index']);
    Route::post('/learners', [LearnerController::class, 'store']);
    Route::get('/learners/{learner}', [LearnerController::class, 'show'])->whereNumber('learner');
    Route::put('/learners/{learner}', [LearnerController::class, 'update'])->whereNumber('learner');
    Route::put('/learners/{learner}/excuse-slip', [LearnerController::class, 'excuseSlip'])->whereNumber('learner');
    Route::get('/learners/re-enrollable', [LearnerController::class, 'reEnrollable']);
    Route::post('/learners/{learner}/enrollments', [LearnerController::class, 'reEnroll'])->whereNumber('learner');

    Route::get('/promotions', [PromotionController::class, 'index']);
    Route::get('/promotions/{schoolYear}', [PromotionController::class, 'show'])->whereNumber('schoolYear');
});

// Learner devices have no login; LearnerSessionController identifies them through the session.
Route::controller(LearnerSessionController::class)->group(function () {
    Route::get('/classroom-session', 'status');
    Route::get('/classroom-session/learners', 'learners');
    Route::get('/classroom-session/me', 'me');
    Route::get('/classroom-session/completion', 'completion');
    Route::get('/classroom-session/practical-result', 'practicalResult');
    Route::post('/classroom-session/claim', 'claim');
    Route::post('/classroom-session/join', 'join');
    Route::post('/classroom-session/finish', 'finish');
    Route::post('/classroom-session/demonstration', 'demonstration');
    Route::get('/activities/{activity}/questions', 'questions')->whereNumber('activity');
});

Route::post('/tts', TtsController::class);

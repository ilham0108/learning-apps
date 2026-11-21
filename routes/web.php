<?php

use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\AttemptController as AdminAttemptController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CandidateController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ExamAssignmentController;
use App\Http\Controllers\Admin\ExamController;
use App\Http\Controllers\Admin\ExamQuestionController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\GradingController;
use App\Http\Controllers\Admin\JobRoleController;
use App\Http\Controllers\Admin\PipelineController;
use App\Http\Controllers\Admin\PositionController;
use App\Http\Controllers\Admin\ProgramAssignmentController;
use App\Http\Controllers\Admin\ProgramContentController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\QuestionCategoryController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\QuestionImportController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\ConsentController;
use App\Http\Controllers\Candidate\DashboardController as CandidateDashboardController;
use App\Http\Controllers\Candidate\ExamController as CandidateExamController;
use App\Http\Controllers\Candidate\LearningController;
use App\Http\Controllers\Candidate\ProfileController as CandidateProfileController;
use App\Http\Controllers\CandidateDocumentFileController;
use App\Http\Controllers\ExamRunner\AttemptController;
use App\Http\Controllers\MaterialFileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('activate/{user}/{token}', [ActivationController::class, 'show'])
        ->middleware('throttle:20,1')
        ->name('activation.show');
    Route::post('activate/{user}/{token}', [ActivationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('activation.store');
});

Route::middleware(['auth', 'role:candidate'])->group(function () {
    Route::get('consent', [ConsentController::class, 'show'])->name('consent.show');
    Route::post('consent', [ConsentController::class, 'store'])->name('consent.store');
});

// Tujuan setelah login untuk semua role; Super Admin dan HR diarahkan ke /admin.
Route::middleware(['auth', 'consent'])->group(function () {
    Route::get('dashboard', CandidateDashboardController::class)->name('dashboard');

    // File materi privat: wajib login, URL bertanda tangan (10 menit), dan izin Policy.
    Route::get('materials/{material}/file', MaterialFileController::class)
        ->middleware('signed')
        ->name('materials.file');
});

Route::middleware(['auth', 'role:candidate', 'consent'])->prefix('learn')->name('learn.')->group(function () {
    Route::get('/', [LearningController::class, 'index'])->name('index');
    Route::get('{program}', [LearningController::class, 'show'])->name('show');
    Route::get('{program}/materials/{material}', [LearningController::class, 'material'])->name('material');
    Route::post('{program}/materials/{material}/complete', [LearningController::class, 'complete'])
        ->middleware('throttle:60,1')
        ->name('material.complete');
});

// Dokumen kandidat (CV, KTP, foto, sertifikat): wajib login, URL bertanda tangan, dan izin Policy.
Route::middleware(['auth', 'consent', 'signed'])
    ->get('candidate-documents/{document}/file', CandidateDocumentFileController::class)
    ->name('candidate-documents.file');

// Data diri kandidat. Nama route berbeda dari `profile.*` milik halaman pengaturan akun.
Route::middleware(['auth', 'role:candidate', 'consent'])->prefix('profile')->name('candidate-profile.')
    ->controller(CandidateProfileController::class)->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::put('personal', 'updatePersonal')->name('personal.update');
        Route::post('educations', 'storeEducation')->name('educations.store');
        Route::put('educations/{education}', 'updateEducation')->name('educations.update');
        Route::delete('educations/{education}', 'destroyEducation')->name('educations.destroy');
        Route::post('experiences', 'storeExperience')->name('experiences.store');
        Route::put('experiences/{experience}', 'updateExperience')->name('experiences.update');
        Route::delete('experiences/{experience}', 'destroyExperience')->name('experiences.destroy');
        Route::post('documents', 'storeDocument')->middleware('throttle:20,1')->name('documents.store');
        Route::delete('documents/{document}', 'destroyDocument')->name('documents.destroy');
    });

// Ujian kandidat. Endpoint JSON runner memakai sesi web + CSRF dan rate limit per endpoint.
Route::middleware(['auth', 'role:candidate', 'consent'])->group(function () {
    Route::get('exams', [CandidateExamController::class, 'index'])->name('exams.index');
    Route::get('exams/{assignment}', [CandidateExamController::class, 'show'])->name('exams.show');
    Route::post('exams/{assignment}/start', [CandidateExamController::class, 'start'])
        ->middleware('throttle:attempt-start')
        ->name('exams.start');

    Route::prefix('attempts/{attempt}')->name('attempts.')->controller(AttemptController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::get('result', 'result')->name('result');
        Route::get('state', 'state')->middleware('throttle:attempt-state')->name('state');
        Route::get('clock', 'clock')->middleware('throttle:attempt-clock')->name('clock');
        Route::put('answers/{question}', 'saveAnswer')->middleware('throttle:attempt-answer')->name('answers.update');
        Route::post('position', 'position')->middleware('throttle:attempt-position')->name('position');
        Route::post('events', 'event')->middleware('throttle:attempt-event')->name('events.store');
        Route::post('submit', 'submit')->middleware('throttle:attempt-submit')->name('submit');
    });
});

Route::middleware(['auth', 'role:super_admin,hr'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('positions', [PositionController::class, 'index'])->middleware('permission:campaigns.manage')->name('positions.index');
    Route::post('departments', [PositionController::class, 'storeDepartment'])->middleware('permission:campaigns.manage')->name('departments.store');
    Route::patch('departments/{department}', [PositionController::class, 'updateDepartment'])->middleware('permission:campaigns.manage')->name('departments.update');
    Route::post('positions', [PositionController::class, 'storePosition'])->middleware('permission:campaigns.manage')->name('positions.store');
    Route::patch('positions/{position}', [PositionController::class, 'updatePosition'])->middleware('permission:campaigns.manage')->name('positions.update');

    // Form tambah/ubah ditampilkan sebagai modal, sehingga tidak ada halaman create/edit.
    Route::resource('campaigns', CampaignController::class)->except(['create', 'edit', 'destroy']);
    Route::put('campaigns/{campaign}/team', [CampaignController::class, 'updateTeam'])->name('campaigns.team.update');

    Route::resource('candidates', CandidateController::class)->except(['create', 'edit', 'destroy']);
    Route::patch('candidates/{candidate}/status', [CandidateController::class, 'updateStatus'])->name('candidates.status.update');
    Route::post('candidates/{candidate}/activation-link', [CandidateController::class, 'activationLink'])
        ->middleware('throttle:10,1')
        ->name('candidates.activation-link.store');

    Route::patch('applications/{application}/stage', [ApplicationController::class, 'updateStage'])->name('applications.stage.update');
    Route::post('applications/{application}/notes', [ApplicationController::class, 'storeNote'])->name('applications.notes.store');
    Route::patch('notes/{note}', [ApplicationController::class, 'updateNote'])->name('notes.update');
    Route::delete('notes/{note}', [ApplicationController::class, 'destroyNote'])->name('notes.destroy');
    Route::put('applications/{application}/evaluation', [ApplicationController::class, 'updateEvaluation'])->name('applications.evaluation.update');

    Route::resource('programs', ProgramController::class)->except(['create', 'edit', 'destroy']);
    Route::post('programs/{program}/modules', [ProgramContentController::class, 'storeModule'])->name('modules.store');
    Route::patch('modules/{module}', [ProgramContentController::class, 'updateModule'])->name('modules.update');
    Route::delete('modules/{module}', [ProgramContentController::class, 'destroyModule'])->name('modules.destroy');
    Route::post('modules/{module}/move', [ProgramContentController::class, 'moveModule'])->name('modules.move');
    Route::get('modules/{module}/materials/create', [ProgramContentController::class, 'createMaterial'])->name('materials.create');
    Route::post('modules/{module}/materials', [ProgramContentController::class, 'storeMaterial'])->name('materials.store');
    Route::get('materials/{material}/edit', [ProgramContentController::class, 'editMaterial'])->name('materials.edit');
    // POST (bukan PUT) karena form dapat berisi unggahan file.
    Route::post('materials/{material}', [ProgramContentController::class, 'updateMaterial'])->name('materials.update');
    Route::delete('materials/{material}', [ProgramContentController::class, 'destroyMaterial'])->name('materials.destroy');
    Route::post('materials/{material}/restore', [ProgramContentController::class, 'restoreMaterial'])->name('materials.restore');
    Route::post('materials/{material}/move', [ProgramContentController::class, 'moveMaterial'])->name('materials.move');
    Route::post('programs/{program}/assignments', [ProgramAssignmentController::class, 'store'])->name('programs.assignments.store');
    Route::delete('program-assignments/{assignment}', [ProgramAssignmentController::class, 'destroy'])->name('program-assignments.destroy');

    Route::get('question-categories', [QuestionCategoryController::class, 'index'])->middleware('permission:exams.manage')->name('question-categories.index');
    Route::post('question-categories', [QuestionCategoryController::class, 'store'])->middleware('permission:exams.manage')->name('question-categories.store');
    Route::patch('question-categories/{category}', [QuestionCategoryController::class, 'update'])->middleware('permission:exams.manage')->name('question-categories.update');
    Route::get('question-imports/template', [QuestionImportController::class, 'template'])->middleware('permission:exams.manage')->name('question-imports.template');
    Route::post('question-imports', [QuestionImportController::class, 'store'])->middleware(['permission:exams.manage', 'throttle:10,1'])->name('question-imports.store');
    Route::delete('questions', [QuestionController::class, 'destroyMany'])->middleware('permission:exams.manage')->name('questions.destroy-many');
    Route::resource('questions', QuestionController::class)->except('show')->middleware('permission:exams.manage');
    Route::patch('questions/{question}/status', [QuestionController::class, 'updateStatus'])->middleware('permission:exams.manage')->name('questions.status.update');

    Route::resource('exams', ExamController::class)->except('destroy');
    Route::post('exams/{exam}/duplicate', [ExamController::class, 'duplicate'])->name('exams.duplicate');
    Route::get('exams/{exam}/question-picker', [ExamQuestionController::class, 'picker'])->name('exams.question-picker');
    Route::post('exams/{exam}/questions', [ExamQuestionController::class, 'store'])->name('exams.questions.store');
    Route::patch('exams/{exam}/questions/{question}', [ExamQuestionController::class, 'update'])->name('exams.questions.update');
    Route::delete('exams/{exam}/questions/{question}', [ExamQuestionController::class, 'destroy'])->name('exams.questions.destroy');
    Route::post('exams/{exam}/questions/{question}/move', [ExamQuestionController::class, 'move'])->name('exams.questions.move');
    Route::post('exams/{exam}/assignments', [ExamAssignmentController::class, 'store'])->name('exams.assignments.store');
    Route::patch('exam-assignments/{assignment}', [ExamAssignmentController::class, 'update'])->name('exam-assignments.update');
    Route::delete('exam-assignments/{assignment}', [ExamAssignmentController::class, 'destroy'])->name('exam-assignments.destroy');
    Route::post('exams/{exam}/release-results', [ExamController::class, 'releaseResults'])->name('exams.release-results');

    Route::get('exams/{exam}/attempts', [AdminAttemptController::class, 'index'])->name('exams.attempts.index');
    Route::get('attempts/{attempt}', [AdminAttemptController::class, 'show'])->name('attempts.show');
    Route::put('attempts/{attempt}/answers/{question}/grade', [AdminAttemptController::class, 'grade'])->name('attempts.grade');
    Route::get('grading', [GradingController::class, 'index'])->middleware('permission:grading.grade')->name('grading.index');

    Route::get('reports', [ReportController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');
    Route::get('exports', [ExportController::class, 'index'])->middleware('permission:reports.export')->name('exports.index');
    Route::post('exports', [ExportController::class, 'store'])->middleware(['permission:reports.export', 'throttle:report-export'])->name('exports.store');
    Route::get('exports/{export}/download', [ExportController::class, 'download'])->middleware('permission:reports.export')->name('exports.download');
    Route::middleware('permission:pipeline.manage_default')->prefix('pipeline')->name('pipeline.')->group(function () {
        Route::get('/', [PipelineController::class, 'index'])->name('index');
        Route::post('stages', [PipelineController::class, 'storeDefault'])->name('stages.store');
        Route::patch('stages/{stage}', [PipelineController::class, 'updateDefault'])->name('stages.update');
        Route::post('stages/{stage}/move', [PipelineController::class, 'moveDefault'])->name('stages.move');
        Route::delete('stages/{stage}', [PipelineController::class, 'destroyDefault'])->name('stages.destroy');
    });

    Route::post('campaigns/{campaign}/stages', [PipelineController::class, 'storeCampaign'])->name('campaigns.stages.store');
    Route::patch('campaigns/{campaign}/stages/{stage}', [PipelineController::class, 'updateCampaign'])->name('campaigns.stages.update');
    Route::post('campaigns/{campaign}/stages/{stage}/move', [PipelineController::class, 'moveCampaign'])->name('campaigns.stages.move');
    Route::delete('campaigns/{campaign}/stages/{stage}', [PipelineController::class, 'destroyCampaign'])->name('campaigns.stages.destroy');

    Route::middleware('permission:staff.manage')->group(function () {
        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::patch('staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::post('staff/{staff}/activation-link', [StaffController::class, 'activationLink'])
            ->middleware('throttle:10,1')
            ->name('staff.activation-link.store');
    });

    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('job-roles', [JobRoleController::class, 'index'])->name('job-roles.index');
        Route::post('job-roles', [JobRoleController::class, 'store'])->name('job-roles.store');
        Route::put('job-roles/{jobRole}', [JobRoleController::class, 'update'])->name('job-roles.update');
        Route::delete('job-roles/{jobRole}', [JobRoleController::class, 'destroy'])->name('job-roles.destroy');
    });

    Route::get('audit-log', [AuditLogController::class, 'index'])->middleware('permission:audit.view')->name('audit-log.index');
});

require __DIR__.'/settings.php';

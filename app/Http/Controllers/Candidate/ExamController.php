<?php

namespace App\Http\Controllers\Candidate;

use App\Domain\Exams\Actions\StartAttempt;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\Exams\Queries\CandidateExams;
use App\Domain\Exams\Queries\ExamAvailability;
use App\Domain\Exams\Support\ResultVisibility;
use App\Domain\Learning\Models\LearningProgram;
use App\Http\Controllers\Controller;
use App\Http\Resources\Exams\AttemptPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar ujian kandidat, halaman instruksi, dan tombol mulai. Ketersediaan
 * dihitung oleh ExamAvailability, yang juga dipakai StartAttempt.
 */
class ExamController extends Controller
{
    public function __construct(
        private readonly ExamAvailability $availability,
        private readonly CandidateExams $exams,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('candidate/exams/index', [
            'exams' => $this->exams->rows($request->user()),
        ]);
    }

    public function show(ExamAssignment $assignment): Response
    {
        $this->authorize('take', $assignment);

        $assignment->load(['exam', 'application']);
        $this->exams->closeExpired($assignment);
        $check = $this->availability->check($assignment);
        $exam = $assignment->exam;

        return Inertia::render('candidate/exams/show', [
            'assignment' => [
                'id' => $assignment->public_id,
                'title' => $exam->title,
                'description' => $exam->description,
                'type' => $exam->type->value,
                'duration_minutes' => $exam->duration_minutes,
                'questions_count' => $exam->examQuestions()->count(),
                'passing_grade' => $exam->passing_grade,
                'navigation' => $exam->navigation->value,
                'opens_at' => $assignment->effectiveOpensAt()?->toIso8601String(),
                'closes_at' => $assignment->effectiveClosesAt()?->toIso8601String(),
                'max_attempts' => $assignment->effectiveMaxAttempts(),
                'attempts_used' => $check['attempts_used'],
                'attempts_left' => $check['attempts_left'],
                'availability' => $check['state'],
                'active_attempt' => $check['active_attempt']?->public_id,
                'incomplete_programs' => array_map(fn (LearningProgram $program) => [
                    'id' => $program->public_id,
                    'name' => $program->name,
                ], $check['incomplete_programs']),
            ],
            'attempts' => $assignment->attempts()->get()
                ->reverse()
                ->map(fn (ExamAttempt $attempt) => AttemptPresenter::summary(
                    $attempt,
                    ResultVisibility::scoreVisible($attempt->setRelation('assignment', $assignment)),
                ))
                ->values()
                ->all(),
        ]);
    }

    public function start(Request $request, ExamAssignment $assignment, StartAttempt $start): RedirectResponse
    {
        $this->authorize('take', $assignment);

        try {
            $attempt = $start($assignment, $request->user());
        } catch (ValidationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => collect($exception->errors())->flatten()->first()]);

            return to_route('exams.show', $assignment);
        }

        return to_route('attempts.show', $attempt);
    }
}

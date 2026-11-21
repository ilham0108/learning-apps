<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Exams\Actions\GradeAnswer;
use App\Domain\Exams\Enums\AttemptEventType;
use App\Domain\Exams\Enums\AttemptStatus;
use App\Domain\Exams\Enums\GradingStatus;
use App\Domain\Exams\Models\AttemptEvent;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAttempt;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GradeAnswerRequest;
use App\Http\Resources\Exams\AttemptPresenter;
use App\Http\Resources\Paginated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monitor attempt per ujian, detail attempt beserta log peristiwa, dan
 * penilaian manual. Semua dibatasi lingkup rekrutmen HR.
 */
class AttemptController extends Controller
{
    public function index(Request $request, Exam $exam): Response
    {
        $this->authorize('view', $exam);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(AttemptStatus::class)],
            'grading' => ['nullable', Rule::enum(GradingStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $attempts = ExamAttempt::query()
            ->visibleTo($request->user())
            ->whereHas('assignment', fn ($query) => $query->where('exam_id', $exam->id))
            ->with(['assignment.application.candidateProfile.user', 'assignment.application.campaign'])
            ->withCount(['events as tab_hidden_count' => fn ($query) => $query->where('type', AttemptEventType::TabHidden)])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['grading'] ?? null, fn ($query, string $grading) => $query->where('grading_status', $grading))
            ->latest('started_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/exams/attempts', [
            'exam' => ['id' => $exam->public_id, 'title' => $exam->title],
            'attempts' => Paginated::make($attempts, fn (ExamAttempt $attempt) => self::row($attempt)),
            'filters' => ['status' => $filters['status'] ?? null, 'grading' => $filters['grading'] ?? null],
        ]);
    }

    public function show(ExamAttempt $attempt): Response
    {
        $this->authorize('grade', $attempt);

        $attempt->load(['assignment.exam', 'assignment.application.candidateProfile.user', 'assignment.application.campaign']);

        return Inertia::render('admin/attempts/show', [
            'exam' => ['id' => $attempt->assignment->exam->public_id, 'title' => $attempt->assignment->exam->title],
            'attempt' => self::row($attempt) + [
                'deadline_at' => $attempt->deadline_at->toIso8601String(),
                'finalized_at' => $attempt->finalized_at?->toIso8601String(),
                'ip_address' => $attempt->ip_address,
                'user_agent' => $attempt->user_agent,
            ],
            'categories' => AttemptPresenter::categoryScores($attempt),
            'questions' => AttemptPresenter::review($attempt, withKey: true, forGrader: true),
            'events' => $attempt->events()->get()->map(fn (AttemptEvent $event) => [
                'type' => $event->type->value,
                'occurred_at' => $event->occurred_at->toIso8601String(),
                'ip_address' => $event->ip_address,
            ])->all(),
        ]);
    }

    public function grade(GradeAnswerRequest $request, ExamAttempt $attempt, string $question, GradeAnswer $grade): RedirectResponse
    {
        $this->authorize('grade', $attempt);

        $graded = $grade(
            $attempt,
            $question,
            $request->validated('points'),
            $request->validated('note'),
            $request->validated('reason'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $graded->isGraded()
            ? __('Nilai disimpan. Semua jawaban sudah dinilai.')
            : __('Nilai disimpan.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(ExamAttempt $attempt): array
    {
        $profile = $attempt->assignment->application->candidateProfile;

        return AttemptPresenter::summary($attempt, true) + [
            'candidate' => [
                'id' => $profile->public_id,
                'name' => $profile->user->name,
                'candidate_number' => $profile->candidate_number,
            ],
            'campaign' => $attempt->assignment->application->campaign->name,
            'tab_hidden_count' => $attempt->tab_hidden_count ?? $attempt->events()->where('type', AttemptEventType::TabHidden)->count(),
        ];
    }
}

<?php

namespace App\Http\Controllers\ExamRunner;

use App\Domain\Exams\Actions\MoveToQuestion;
use App\Domain\Exams\Actions\SaveAnswer;
use App\Domain\Exams\Actions\SubmitAttempt;
use App\Domain\Exams\Enums\AttemptEventType;
use App\Domain\Exams\Exceptions\AttemptRejected;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\Exams\Support\AttemptAccess;
use App\Domain\Exams\Support\ResultVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExamRunner\SaveAnswerRequest;
use App\Http\Resources\Exams\AttemptPresenter;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman runner dan endpoint JSON pengerjaan ujian (Tahap 1 Bagian 8). Setiap
 * endpoint menjalankan cek kedaluwarsa saat akses; penolakan dikirim sebagai
 * HTTP 409 dengan kode agar runner dapat bereaksi (pindah ke hasil, dsb.).
 */
class AttemptController extends Controller
{
    public function __construct(private readonly AttemptAccess $access) {}

    public function show(ExamAttempt $attempt): Response|RedirectResponse
    {
        $this->authorize('take', $attempt);

        $attempt = $this->access->closeIfExpired($attempt);

        if (! $attempt->isInProgress()) {
            return to_route('attempts.result', $attempt);
        }

        $attempt->load('assignment.exam');

        return Inertia::render('candidate/attempts/runner', [
            'exam' => [
                'title' => $attempt->assignment->exam->title,
                'assignment' => $attempt->assignment->public_id,
            ],
            'sessionToken' => $this->access->openSession($attempt),
            ...AttemptPresenter::runnerState($attempt),
        ]);
    }

    public function state(ExamAttempt $attempt): JsonResponse
    {
        return $this->guarded($attempt, fn (ExamAttempt $active) => AttemptPresenter::runnerState($active));
    }

    public function clock(ExamAttempt $attempt): JsonResponse
    {
        $this->authorize('take', $attempt);

        $attempt = $this->access->closeIfExpired($attempt);

        return response()->json(AttemptPresenter::clock($attempt) + $this->resultLink($attempt));
    }

    public function saveAnswer(SaveAnswerRequest $request, ExamAttempt $attempt, string $question, SaveAnswer $save): JsonResponse
    {
        return $this->guarded($attempt, function (ExamAttempt $active) use ($request, $question, $save) {
            $answer = $save($active, $question, $request->validated());

            return [
                'question' => $question,
                'revision' => $answer->client_revision,
                'saved_at' => $answer->saved_at?->toIso8601String(),
            ];
        });
    }

    public function position(Request $request, ExamAttempt $attempt, MoveToQuestion $move): JsonResponse
    {
        $data = $request->validate(['position' => ['required', 'integer', 'min:1']]);

        return $this->guarded($attempt, function (ExamAttempt $active) use ($data, $move) {
            $moved = $move($active, $data['position']);

            return [
                'current_position' => $moved->current_position,
                'furthest_position' => $moved->furthest_position,
            ];
        });
    }

    /**
     * Log pindah tab. Bahan pertimbangan HR, bukan bukti kecurangan otomatis.
     */
    public function event(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.AttemptEventType::TabHidden->value.','.AttemptEventType::TabVisible->value],
        ]);

        return $this->guarded($attempt, function (ExamAttempt $active) use ($data) {
            $this->access->event($active, AttemptEventType::from($data['type']));

            return ['recorded' => true];
        });
    }

    /**
     * Submit idempotent: attempt yang sudah selesai mengembalikan status final yang sama.
     */
    public function submit(ExamAttempt $attempt, SubmitAttempt $submit): JsonResponse
    {
        $this->authorize('take', $attempt);

        $attempt = $this->access->closeIfExpired($attempt);

        if ($attempt->isInProgress()) {
            try {
                $this->access->ensureActive($attempt);
            } catch (AttemptRejected $rejected) {
                return $this->rejected($rejected, $attempt);
            }

            $attempt = $submit($attempt);
        }

        return response()->json(['status' => $attempt->status->value] + $this->resultLink($attempt));
    }

    public function result(ExamAttempt $attempt): Response|RedirectResponse
    {
        $this->authorize('take', $attempt);

        $attempt = $this->access->closeIfExpired($attempt);

        if ($attempt->isInProgress()) {
            return to_route('attempts.show', $attempt);
        }

        $attempt->load('assignment.exam');
        $scoreVisible = ResultVisibility::scoreVisible($attempt);
        $reviewVisible = ResultVisibility::reviewVisible($attempt);

        return Inertia::render('candidate/attempts/result', [
            'exam' => [
                'title' => $attempt->assignment->exam->title,
                'assignment' => $attempt->assignment->public_id,
                'result_release' => $attempt->result_release->value,
            ],
            'attempt' => AttemptPresenter::summary($attempt, $scoreVisible),
            'categories' => $scoreVisible ? AttemptPresenter::categoryScores($attempt) : [],
            'review' => $reviewVisible ? AttemptPresenter::review($attempt, withKey: true) : null,
        ]);
    }

    /**
     * @param  Closure(ExamAttempt): array<string, mixed>  $callback
     */
    private function guarded(ExamAttempt $attempt, Closure $callback): JsonResponse
    {
        $this->authorize('take', $attempt);

        try {
            $active = $this->access->ensureActive($attempt);

            return response()->json($callback($active));
        } catch (AttemptRejected $rejected) {
            return $this->rejected($rejected, $attempt->refresh());
        }
    }

    private function rejected(AttemptRejected $rejected, ExamAttempt $attempt): JsonResponse
    {
        return response()->json([
            'code' => $rejected->reason,
            'message' => $rejected->getMessage(),
            'status' => $attempt->status->value,
        ] + $this->resultLink($attempt), 409);
    }

    /**
     * @return array{result_url?: string}
     */
    private function resultLink(ExamAttempt $attempt): array
    {
        return $attempt->isInProgress() ? [] : ['result_url' => route('attempts.result', $attempt, false)];
    }
}

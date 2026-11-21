<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Exams\Enums\AnswerGradingStatus;
use App\Domain\Exams\Enums\AttemptEventType;
use App\Domain\Exams\Enums\GradingStatus;
use App\Domain\Exams\Models\ExamAttempt;
use App\Http\Controllers\Controller;
use App\Http\Resources\Paginated;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Antrean penilaian manual: attempt dalam lingkup HR yang masih memiliki
 * jawaban esai atau isian singkat yang belum dinilai, yang paling lama menunggu di atas.
 */
class GradingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user()->isCandidate(), 404);

        $attempts = ExamAttempt::query()
            ->visibleTo($request->user())
            ->where('grading_status', GradingStatus::PendingManual)
            ->with(['assignment.exam', 'assignment.application.candidateProfile.user', 'assignment.application.campaign'])
            ->withCount([
                'answers as pending_count' => fn ($query) => $query->where('grading_status', AnswerGradingStatus::PendingManual),
                'events as tab_hidden_count' => fn ($query) => $query->where('type', AttemptEventType::TabHidden),
            ])
            ->oldest('submitted_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/grading/index', [
            'attempts' => Paginated::make($attempts, fn (ExamAttempt $attempt) => AttemptController::row($attempt) + [
                'exam' => $attempt->assignment->exam->title,
                'pending_count' => $attempt->pending_count,
            ]),
        ]);
    }
}

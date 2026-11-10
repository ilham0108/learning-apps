<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Exams\Actions\ManageExamQuestions;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamQuestion;
use App\Domain\QuestionBank\Enums\QuestionStatus;
use App\Domain\QuestionBank\Models\Question;
use App\Http\Controllers\Controller;
use App\Http\Resources\Paginated;
use App\Http\Resources\QuestionBank\QuestionListResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Soal di dalam ujian. Soal diidentifikasi dengan public_id soal bank.
 */
class ExamQuestionController extends Controller
{
    /**
     * Pemilih soal: soal aktif versi terbaru yang belum ada di ujian (JSON, tanpa kunci jawaban).
     */
    public function picker(Request $request, Exam $exam): JsonResponse
    {
        $this->authorize('editQuestions', $exam);

        $filters = QuestionController::validateFilters($request);
        $filters['status'] = QuestionStatus::Active->value;

        $questions = QuestionController::filteredQuery($filters)
            ->whereNotIn('id', $exam->examQuestions()->select('question_id'))
            ->with('category')
            ->withCount('children')
            ->latest('id')
            ->paginate(10);

        return response()->json(Paginated::make($questions, QuestionListResource::class));
    }

    public function store(Request $request, Exam $exam, ManageExamQuestions $manage): RedirectResponse
    {
        $this->authorize('editQuestions', $exam);

        $data = $request->validate([
            'questions' => ['required', 'array', 'min:1', 'max:100'],
            'questions.*' => ['distinct', 'ulid'],
        ]);

        $added = $manage->add($exam, Question::query()->whereIn('public_id', $data['questions'])->get());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':count soal ditambahkan ke ujian.', ['count' => $added])]);

        return back();
    }

    public function update(Request $request, Exam $exam, Question $question, ManageExamQuestions $manage): RedirectResponse
    {
        $this->authorize('editQuestions', $exam);

        $points = $request->validate(['points' => ['required', 'numeric', 'between:0.01,1000']], attributes: ['points' => 'bobot'])['points'];
        $manage->updatePoints($exam, $this->examQuestion($exam, $question), number_format((float) $points, 2, '.', ''));

        return back();
    }

    public function destroy(Exam $exam, Question $question, ManageExamQuestions $manage): RedirectResponse
    {
        $this->authorize('editQuestions', $exam);

        $manage->remove($exam, $this->examQuestion($exam, $question));

        return back();
    }

    public function move(Request $request, Exam $exam, Question $question, ManageExamQuestions $manage): RedirectResponse
    {
        $this->authorize('editQuestions', $exam);

        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];
        $manage->move($exam, $this->examQuestion($exam, $question), $direction);

        return back();
    }

    private function examQuestion(Exam $exam, Question $question): ExamQuestion
    {
        return $exam->examQuestions()->where('question_id', $question->id)->firstOrFail();
    }
}

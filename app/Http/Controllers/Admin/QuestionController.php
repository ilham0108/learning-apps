<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Exams\Models\ExamQuestion;
use App\Domain\QuestionBank\Actions\SaveQuestion;
use App\Domain\QuestionBank\Enums\Difficulty;
use App\Domain\QuestionBank\Enums\QuestionStatus;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\QuestionBank\Models\QuestionCategory;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuestionRequest;
use App\Http\Resources\Paginated;
use App\Http\Resources\QuestionBank\QuestionListResource;
use App\Http\Resources\QuestionBank\QuestionResource;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bank soal bersama. Daftar hanya menampilkan versi terbaru soal mandiri dan
 * soal induk studi kasus; sub-soal dikelola melalui soal induknya.
 */
class QuestionController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = self::validateFilters($request);

        $questions = self::filteredQuery($filters)
            ->with('category')
            ->withCount(['children', 'examQuestions'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/questions/index', [
            'questions' => Paginated::make($questions, QuestionListResource::class),
            'filters' => $filters,
            'categories' => self::categoryOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/questions/form', [
            'question' => null,
            'categories' => self::categoryOptions(),
            'usage' => [],
            'lockedUsage' => false,
        ]);
    }

    public function store(QuestionRequest $request, SaveQuestion $saveQuestion): RedirectResponse
    {
        $question = $saveQuestion(null, $request->questionData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Soal berhasil disimpan.')]);

        return to_route('admin.questions.edit', $question);
    }

    public function edit(Question $question): Response
    {
        abort_if($question->parent_id !== null, 404);

        $question->load(['category', 'options', 'acceptedAnswers', 'children.options', 'children.acceptedAnswers']);

        return Inertia::render('admin/questions/form', [
            'question' => QuestionResource::make($question)->resolve(),
            'categories' => self::categoryOptions(),
            'usage' => $this->usage($question),
            'lockedUsage' => SaveQuestion::isUsedByLockedExam($question),
        ]);
    }

    public function update(QuestionRequest $request, Question $question, SaveQuestion $saveQuestion): RedirectResponse
    {
        abort_if($question->parent_id !== null, 404);

        $saved = $saveQuestion($question, $request->questionData(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $saved->is($question)
                ? __('Soal berhasil diperbarui.')
                : __('Soal dipakai ujian yang sudah dikerjakan, sehingga disimpan sebagai versi :version.', ['version' => $saved->version]),
        ]);

        return to_route('admin.questions.edit', $saved);
    }

    /**
     * Mengarsipkan atau mengaktifkan kembali soal. Soal arsip tidak dapat ditambahkan ke ujian.
     */
    public function updateStatus(Request $request, Question $question, ActivityLogger $logger): RedirectResponse
    {
        abort_if($question->parent_id !== null, 404);

        $status = QuestionStatus::from($request->validate(['status' => ['required', Rule::enum(QuestionStatus::class)]])['status']);

        DB::transaction(function () use ($question, $status): void {
            $question->update(['status' => $status]);
            $question->children()->update(['status' => $status]);
        });

        $logger->log($status === QuestionStatus::Archived ? 'question.archived' : 'question.restored', $question);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status soal diperbarui.')]);

        return back();
    }

    /**
     * Soal yang pernah dipakai ujian (versi mana pun) diarsipkan, bukan dihapus (rules Bagian 7).
     */
    public function destroy(Question $question, ActivityLogger $logger): RedirectResponse
    {
        abort_if($question->parent_id !== null, 404);

        if ($question->previous_version_id !== null || ExamQuestion::query()->whereIn('question_id', $this->versionIds($question))->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Soal yang pernah dipakai ujian tidak dapat dihapus. Arsipkan soal ini.')]);

            return back();
        }

        DB::transaction(function () use ($question, $logger): void {
            $logger->log('question.deleted', null, ['question' => $question->public_id]);
            $question->children()->each(fn (Question $child) => $child->delete());
            $question->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Soal berhasil dihapus.')]);

        return to_route('admin.questions.index');
    }

    /**
     * @return array{search: string|null, category: string|null, type: string|null, difficulty: string|null, status: string|null}
     */
    public static function validateFilters(Request $request): array
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'ulid'],
            'type' => ['nullable', Rule::enum(QuestionType::class)],
            'difficulty' => ['nullable', Rule::enum(Difficulty::class)],
            'status' => ['nullable', Rule::enum(QuestionStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return [
            'search' => $data['search'] ?? null,
            'category' => $data['category'] ?? null,
            'type' => $data['type'] ?? null,
            'difficulty' => $data['difficulty'] ?? null,
            'status' => $data['status'] ?? null,
        ];
    }

    /**
     * @param  array{search: string|null, category: string|null, type: string|null, difficulty: string|null, status: string|null}  $filters
     * @return Builder<Question>
     */
    public static function filteredQuery(array $filters): Builder
    {
        return Question::query()
            ->current()
            ->topLevel()
            ->when($filters['search'], fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('body_html', 'like', Search::contains($search))
                ->orWhere('topic', 'like', Search::contains($search))))
            ->when($filters['category'], fn ($query, string $category) => $query->whereIn(
                'question_category_id',
                QuestionCategory::query()->select('id')->where('public_id', $category),
            ))
            ->when($filters['type'], fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['difficulty'], fn ($query, string $difficulty) => $query->where('difficulty', $difficulty))
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status));
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function categoryOptions(): array
    {
        return QuestionCategory::query()->orderBy('name')->get()
            ->map(fn (QuestionCategory $category) => ['id' => $category->public_id, 'name' => $category->name])
            ->values()
            ->all();
    }

    /**
     * Riwayat pemakaian di ujian untuk semua versi soal.
     *
     * @return array<int, array{exam: array{id: string, title: string, status: string, is_locked: bool}, version: int}>
     */
    private function usage(Question $question): array
    {
        $versions = Question::query()->whereIn('id', $this->versionIds($question))->pluck('version', 'id');

        return ExamQuestion::query()
            ->whereIn('question_id', $versions->keys())
            ->with('exam')
            ->latest('id')
            ->get()
            ->map(fn (ExamQuestion $examQuestion) => [
                'exam' => [
                    'id' => $examQuestion->exam->public_id,
                    'title' => $examQuestion->exam->title,
                    'status' => $examQuestion->exam->status->value,
                    'is_locked' => $examQuestion->exam->isLocked(),
                ],
                'version' => (int) $versions[$examQuestion->question_id],
            ])
            ->values()
            ->all();
    }

    /**
     * ID semua versi sebelumnya beserta versi ini.
     *
     * @return list<int>
     */
    private function versionIds(Question $question): array
    {
        $ids = [$question->id];
        $previous = $question->previous_version_id;

        while ($previous !== null) {
            $ids[] = $previous;
            $previous = Question::query()->whereKey($previous)->value('previous_version_id');
        }

        return $ids;
    }
}

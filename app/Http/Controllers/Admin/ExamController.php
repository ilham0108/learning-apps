<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Candidates\Models\Position;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Exams\Actions\DuplicateExam;
use App\Domain\Exams\Actions\SaveExam;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Exams\Models\ExamQuestion;
use App\Domain\Learning\Models\LearningProgram;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExamRequest;
use App\Http\Resources\Exams\ExamResource;
use App\Http\Resources\Paginated;
use App\Support\LocalDateTime;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Exam::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ExamStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $exams = Exam::query()
            ->with('duplicatedFrom')
            ->withCount(['examQuestions', 'assignments'])
            ->withSum('examQuestions as total_points', 'points')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('title', 'like', Search::contains($search)))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/exams/index', [
            'exams' => Paginated::make($exams, ExamResource::class),
            'filters' => ['search' => $filters['search'] ?? null, 'status' => $filters['status'] ?? null],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Exam::class);

        return Inertia::render('admin/exams/form', [
            'exam' => null,
            'programs' => $this->programOptions(),
        ]);
    }

    public function store(ExamRequest $request, SaveExam $saveExam): RedirectResponse
    {
        $this->authorize('create', Exam::class);

        $exam = $saveExam(
            null,
            $request->examData(),
            $request->programIds('prerequisite_programs'),
            $request->programIds('related_programs'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ujian dibuat sebagai draf. Tambahkan soal sebelum menerbitkan.')]);

        return to_route('admin.exams.show', $exam);
    }

    public function show(Request $request, Exam $exam): Response
    {
        $this->authorize('view', $exam);

        $exam->load(['prerequisitePrograms', 'programs', 'duplicatedFrom']);
        $user = $request->user();

        $assignments = ExamAssignment::query()
            ->where('exam_id', $exam->id)
            ->whereIn('recruitment_application_id', RecruitmentApplication::query()->select('id')->visibleTo($user))
            ->with(['application.candidateProfile.user', 'application.campaign'])
            ->withCount('attempts')
            ->latest('id')
            ->get();

        return Inertia::render('admin/exams/show', [
            'exam' => ExamResource::make($exam)->resolve($request),
            'questions' => $exam->examQuestions()->with(['question.category'])->get()
                ->map(fn (ExamQuestion $examQuestion) => [
                    'id' => $examQuestion->question->public_id,
                    'excerpt' => Str::limit(trim(html_entity_decode(strip_tags($examQuestion->question->body_html))), 160),
                    'type' => $examQuestion->question->type->value,
                    'category' => $examQuestion->question->category->name,
                    'difficulty' => $examQuestion->question->difficulty->value,
                    'version' => $examQuestion->question->version,
                    'is_superseded' => $examQuestion->question->superseded_at !== null,
                    'points' => $examQuestion->points,
                ])
                ->all(),
            'assignments' => $assignments->map(fn (ExamAssignment $assignment) => [
                'id' => $assignment->public_id,
                'candidate' => [
                    'id' => $assignment->application->candidateProfile->public_id,
                    'name' => $assignment->application->candidateProfile->user->name,
                    'candidate_number' => $assignment->application->candidateProfile->candidate_number,
                ],
                'campaign' => $assignment->application->campaign->name,
                'assigned_via' => $assignment->assigned_via->value,
                'opens_at' => $assignment->effectiveOpensAt()?->toIso8601String(),
                'closes_at' => $assignment->effectiveClosesAt()?->toIso8601String(),
                'max_attempts' => $assignment->effectiveMaxAttempts(),
                'has_override' => $assignment->opens_at_override !== null
                    || $assignment->closes_at_override !== null
                    || $assignment->max_attempts_override !== null,
                'override' => [
                    'opens_at' => LocalDateTime::toInput($assignment->opens_at_override),
                    'closes_at' => LocalDateTime::toInput($assignment->closes_at_override),
                    'max_attempts' => $assignment->max_attempts_override,
                ],
                'attempts_count' => $assignment->attempts_count ?? 0,
            ])->all(),
            'assignOptions' => [
                'campaigns' => RecruitmentCampaign::query()->visibleTo($user)->orderByDesc('starts_on')->get()
                    ->map(fn (RecruitmentCampaign $campaign) => ['id' => $campaign->public_id, 'name' => $campaign->name])->all(),
                'positions' => Position::query()->with('department')->where('is_active', true)->orderBy('name')->get()
                    ->map(fn (Position $position) => [
                        'id' => $position->public_id,
                        'name' => $position->name,
                        'department' => $position->department->name,
                    ])->all(),
            ],
            'categories' => QuestionController::categoryOptions(),
        ]);
    }

    public function edit(Exam $exam): Response
    {
        $this->authorize('update', $exam);

        $exam->load(['prerequisitePrograms', 'programs', 'duplicatedFrom']);

        return Inertia::render('admin/exams/form', [
            'exam' => ExamResource::make($exam)->resolve(),
            'programs' => $this->programOptions(),
        ]);
    }

    public function update(ExamRequest $request, Exam $exam, SaveExam $saveExam): RedirectResponse
    {
        $this->authorize('update', $exam);

        $saveExam(
            $exam,
            $request->examData(),
            $request->programIds('prerequisite_programs'),
            $request->programIds('related_programs'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ujian berhasil diperbarui.')]);

        return to_route('admin.exams.show', $exam);
    }

    public function duplicate(Request $request, Exam $exam, DuplicateExam $duplicateExam): RedirectResponse
    {
        $this->authorize('create', Exam::class);

        $copy = $duplicateExam($exam, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ujian berhasil diduplikasi sebagai draf.')]);

        return to_route('admin.exams.show', $copy);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function programOptions(): array
    {
        return LearningProgram::query()->orderBy('name')->get()
            ->map(fn (LearningProgram $program) => ['id' => $program->public_id, 'name' => $program->name])
            ->values()
            ->all();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Candidates\Models\Position;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Learning\Actions\SaveProgram;
use App\Domain\Learning\Enums\ProgramStatus;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Models\ProgramAssignment;
use App\Domain\Learning\Queries\ProgramProgress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProgramRequest;
use App\Http\Resources\Learning\ProgramDetailResource;
use App\Http\Resources\Learning\ProgramSummaryResource;
use App\Http\Resources\Paginated;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LearningProgram::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProgramStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $programs = LearningProgram::query()
            ->with('targetPosition')
            ->withCount(['modules', 'materials' => fn ($query) => $query->whereNull('archived_at'), 'assignments'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', Search::contains($search)))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/programs/index', [
            'programs' => Paginated::make($programs, ProgramSummaryResource::class),
            'filters' => ['search' => $filters['search'] ?? null, 'status' => $filters['status'] ?? null],
            // Pilihan target posisi untuk modal "Buat program".
            'positions' => $this->positionOptions(),
        ]);
    }

    public function store(ProgramRequest $request, SaveProgram $saveProgram): RedirectResponse
    {
        $this->authorize('create', LearningProgram::class);

        $program = $saveProgram(null, $request->programData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Program berhasil dibuat. Tambahkan modul dan materi.')]);

        return to_route('admin.programs.show', $program);
    }

    public function show(Request $request, LearningProgram $program, ProgramProgress $progress): Response
    {
        $this->authorize('view', $program);

        $program->load(['targetPosition', 'modules.materials']);
        $user = $request->user();

        // Penugasan hanya untuk lamaran dalam lingkup pengguna.
        $assignments = ProgramAssignment::query()
            ->where('learning_program_id', $program->id)
            ->whereIn('recruitment_application_id', RecruitmentApplication::query()->select('id')->visibleTo($user))
            ->with(['application.candidateProfile.user', 'application.campaign'])
            ->latest('id')
            ->get();

        $summaries = $progress->forProgram(
            $program->id,
            $assignments->map(fn (ProgramAssignment $assignment) => $assignment->application->candidate_profile_id)->unique()->values()->all(),
        );

        return Inertia::render('admin/programs/show', [
            'program' => ProgramDetailResource::make($program)->resolve($request),
            'assignments' => $assignments->map(fn (ProgramAssignment $assignment) => [
                'id' => $assignment->public_id,
                'candidate' => [
                    'id' => $assignment->application->candidateProfile->public_id,
                    'name' => $assignment->application->candidateProfile->user->name,
                    'candidate_number' => $assignment->application->candidateProfile->candidate_number,
                ],
                'campaign' => $assignment->application->campaign->name,
                'assigned_via' => $assignment->assigned_via->value,
                'due_at' => $assignment->due_at?->toIso8601String(),
                'progress' => $summaries[$assignment->application->candidate_profile_id]->toArray(),
            ])->all(),
            'assignOptions' => [
                'campaigns' => RecruitmentCampaign::query()->visibleTo($user)->orderByDesc('starts_on')->get()
                    ->map(fn (RecruitmentCampaign $campaign) => ['id' => $campaign->public_id, 'name' => $campaign->name])->all(),
                'positions' => $this->positionOptions(),
            ],
        ]);
    }

    public function update(ProgramRequest $request, LearningProgram $program, SaveProgram $saveProgram): RedirectResponse
    {
        $this->authorize('update', $program);

        $saveProgram($program, $request->programData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Program berhasil diperbarui.')]);

        return to_route('admin.programs.show', $program);
    }

    /**
     * @return array<int, array{id: string, name: string, department: string}>
     */
    private function positionOptions(): array
    {
        return Position::query()->with('department')->where('is_active', true)->orderBy('name')->get()
            ->map(fn (Position $position) => [
                'id' => $position->public_id,
                'name' => $position->name,
                'department' => $position->department->name,
            ])
            ->values()
            ->all();
    }
}

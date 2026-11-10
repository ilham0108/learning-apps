<?php

namespace App\Http\Controllers\Candidate;

use App\Domain\Learning\Actions\RecordMaterialProgress;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\LearningModule;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Models\MaterialProgress;
use App\Domain\Learning\Models\ProgramAssignment;
use App\Domain\Learning\Queries\ProgramProgress;
use App\Domain\Learning\Support\MaterialContent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman belajar kandidat. Hanya program terbit yang ditugaskan dan materi aktif
 * yang terlihat; membuka materi mencatat waktu pertama dibuka.
 */
class LearningController extends Controller
{
    public function index(Request $request, ProgramProgress $progress): Response
    {
        $user = $request->user();
        $programs = LearningProgram::query()->assignedToCandidate($user)->orderBy('name')->get();
        $summaries = $progress->forCandidate($user->candidateProfile->id, $programs->map(fn (LearningProgram $program) => $program->id)->all());
        $dueDates = $this->dueDates($request, $programs);

        return Inertia::render('candidate/learn/index', [
            'programs' => $programs->map(fn (LearningProgram $program) => [
                'id' => $program->public_id,
                'name' => $program->name,
                'description' => $program->description,
                'category' => $program->category,
                'estimated_minutes' => $program->estimated_minutes,
                'due_at' => $dueDates[$program->id] ?? null,
                'progress' => $summaries[$program->id]->toArray(),
            ])->all(),
        ]);
    }

    public function show(Request $request, LearningProgram $program, ProgramProgress $progress): Response
    {
        $this->authorize('view', $program);

        $profile = $request->user()->candidateProfile;
        $program->load(['modules.materials' => fn ($query) => $query->active()]);
        $states = $this->materialStates($profile->id, $program);

        return Inertia::render('candidate/learn/show', [
            'program' => [
                'id' => $program->public_id,
                'name' => $program->name,
                'description' => $program->description,
                'estimated_minutes' => $program->estimated_minutes,
                'due_at' => $this->dueDates($request, collect([$program]))[$program->id] ?? null,
                'progress' => $progress->forCandidate($profile->id, [$program->id])[$program->id]->toArray(),
                'modules' => $program->modules->map(fn (LearningModule $module) => [
                    'id' => $module->public_id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'materials' => $module->materials->map(fn (LearningMaterial $material) => [
                        'id' => $material->public_id,
                        'title' => $material->title,
                        'type' => $material->type->value,
                        'is_required' => $material->is_required,
                        'status' => $this->statusOf($states->get($material->id)),
                    ])->values()->all(),
                ])->values()->all(),
            ],
        ]);
    }

    public function material(Request $request, LearningProgram $program, LearningMaterial $material, RecordMaterialProgress $record): Response
    {
        $this->authorize('view', $program);
        abort_unless($material->module->learning_program_id === $program->id, 404);
        $this->authorize('view', $material);

        $progress = $record->opened($request->user()->candidateProfile, $material);

        $program->load(['modules.materials' => fn ($query) => $query->active()]);
        $ordered = $program->modules->flatMap(fn (LearningModule $module) => $module->materials)->values();
        $index = $ordered->search(fn (LearningMaterial $item) => $item->id === $material->id);
        $neighbour = fn (int $offset) => ($item = $ordered->get($index + $offset)) === null ? null : [
            'id' => $item->public_id,
            'title' => $item->title,
        ];

        return Inertia::render('candidate/learn/material', [
            'program' => ['id' => $program->public_id, 'name' => $program->name],
            'module' => ['title' => $material->module->title],
            'material' => [
                'id' => $material->public_id,
                'title' => $material->title,
                'type' => $material->type->value,
                'is_required' => $material->is_required,
                'completed_at' => $progress->completed_at?->toIso8601String(),
                ...MaterialContent::for($material),
            ],
            'previous' => $index === false ? null : $neighbour(-1),
            'next' => $index === false ? null : $neighbour(1),
        ]);
    }

    public function complete(Request $request, LearningProgram $program, LearningMaterial $material, RecordMaterialProgress $record): RedirectResponse
    {
        $this->authorize('view', $program);
        abort_unless($material->module->learning_program_id === $program->id, 404);
        $this->authorize('complete', $material);

        $record->completed($request->user()->candidateProfile, $material);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Materi ditandai selesai.')]);

        return back();
    }

    /**
     * @return Collection<int, MaterialProgress> Kunci: ID materi.
     */
    private function materialStates(int $profileId, LearningProgram $program): Collection
    {
        return MaterialProgress::query()
            ->where('candidate_profile_id', $profileId)
            ->whereIn('learning_material_id', $program->modules
                ->flatMap(fn (LearningModule $module) => $module->materials)
                ->map(fn (LearningMaterial $material) => $material->id)
                ->all())
            ->get()
            ->keyBy('learning_material_id');
    }

    private function statusOf(?MaterialProgress $progress): string
    {
        return match (true) {
            $progress?->completed_at !== null => 'completed',
            $progress?->first_opened_at !== null => 'in_progress',
            default => 'not_started',
        };
    }

    /**
     * Batas waktu terdekat per program dari penugasan milik kandidat.
     *
     * @param  Collection<int, LearningProgram>  $programs
     * @return array<int, string>
     */
    private function dueDates(Request $request, Collection $programs): array
    {
        return ProgramAssignment::query()
            ->whereIn('learning_program_id', $programs->map(fn (LearningProgram $program) => $program->id)->all())
            ->whereIn('recruitment_application_id', $request->user()->candidateProfile->applications()->select('id'))
            ->whereNotNull('due_at')
            ->get()
            ->groupBy('learning_program_id')
            ->map(fn (Collection $assignments) => $assignments->min('due_at')?->toIso8601String())
            ->filter()
            ->all();
    }
}

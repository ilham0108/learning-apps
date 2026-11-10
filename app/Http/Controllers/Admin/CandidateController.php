<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\Actions\CreateActivationLink;
use App\Domain\Candidates\Actions\RegisterCandidate;
use App\Domain\Candidates\Actions\SetCandidateActive;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Learning\Enums\ProgramStatus;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Models\ProgramAssignment;
use App\Domain\Learning\Queries\ProgramProgress;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CandidateIndexRequest;
use App\Http\Requests\Admin\CandidateStatusRequest;
use App\Http\Requests\Admin\StoreCandidateRequest;
use App\Http\Requests\Admin\UpdateCandidateRequest;
use App\Http\Resources\Candidates\CandidateDetailResource;
use App\Http\Resources\Candidates\CandidateListResource;
use App\Http\Resources\Paginated;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CandidateController extends Controller
{
    public function index(CandidateIndexRequest $request): Response
    {
        $this->authorize('viewAny', CandidateProfile::class);

        $user = $request->user();
        $filters = $request->filters();

        $candidates = CandidateProfile::query()
            ->visibleTo($user)
            ->join('users', 'users.id', '=', 'candidate_profiles.user_id')
            ->select('candidate_profiles.*')
            ->with([
                'user',
                'applications' => fn ($query) => $query->visibleTo($user)->with('campaign.position')->latest('applied_at'),
            ])
            ->when($filters['search'], fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('users.name', 'like', Search::contains($search))
                ->orWhere('users.email', 'like', Search::contains($search))
                ->orWhere('candidate_profiles.candidate_number', 'like', Search::contains($search))))
            // Filter tahap dan gelombang hanya melihat lamaran dalam lingkup pengguna.
            ->when($filters['campaign'] || $filters['stage'], fn ($query) => $query->whereHas('applications', fn ($query) => $query
                ->visibleTo($user)
                ->when($filters['campaign'], fn ($query, string $campaign) => $query->whereIn(
                    'recruitment_campaign_id',
                    RecruitmentCampaign::query()->select('id')->where('public_id', $campaign),
                ))
                ->when($filters['stage'], fn ($query, string $stage) => $query->where('stage', $stage))))
            ->when($filters['status'], fn ($query, string $status) => $query->where('users.is_active', $status === 'active'))
            ->orderBy(match ($filters['sort']) {
                'name' => 'users.name',
                'candidate_number' => 'candidate_profiles.candidate_number',
                default => 'candidate_profiles.registered_on',
            }, $filters['direction'])
            ->orderBy('candidate_profiles.id', $filters['direction'])
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/candidates/index', [
            'candidates' => Paginated::make($candidates, CandidateListResource::class),
            'filters' => $filters,
            'campaignOptions' => $this->campaignOptions(RecruitmentCampaign::query()->visibleTo($user)),
            // Gelombang yang masih menerima kandidat, untuk modal "Tambah kandidat".
            'openCampaignOptions' => $this->campaignOptions(StoreCandidateRequest::openCampaigns($request)),
        ]);
    }

    public function store(StoreCandidateRequest $request, RegisterCandidate $registerCandidate): RedirectResponse
    {
        $this->authorize('create', CandidateProfile::class);

        $application = $registerCandidate(
            $request->candidateData(),
            $request->campaign(),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kandidat berhasil ditambahkan ke gelombang.')]);

        return to_route('admin.candidates.show', $application->candidateProfile);
    }

    public function show(Request $request, CandidateProfile $candidate, ProgramProgress $progress): Response
    {
        $this->authorize('view', $candidate);

        $candidate->load([
            'user',
            'applications' => fn ($query) => $query
                ->visibleTo($request->user())
                ->with([
                    'campaign.position.department',
                    'stageHistories.changer',
                    'internalNotes.author',
                    'evaluations.evaluator',
                    'evaluations.scores',
                    'programAssignments.program',
                ])
                ->latest('applied_at'),
        ]);

        $assignments = $candidate->applications->flatMap(fn (RecruitmentApplication $application) => $application->programAssignments);
        $summaries = $progress->forCandidate(
            $candidate->id,
            $assignments->map(fn (ProgramAssignment $assignment) => $assignment->learning_program_id)->unique()->values()->all(),
        );

        return Inertia::render('admin/candidates/show', [
            'candidate' => CandidateDetailResource::make($candidate)->resolve($request),
            'canUpdate' => $request->user()->can('update', $candidate),
            // Program per lamaran beserta progres kandidat.
            'programAssignments' => $candidate->applications->mapWithKeys(fn (RecruitmentApplication $application) => [
                $application->public_id => $application->programAssignments->map(fn (ProgramAssignment $assignment) => [
                    'id' => $assignment->public_id,
                    'program' => ['id' => $assignment->program->public_id, 'name' => $assignment->program->name],
                    'due_at' => $assignment->due_at?->toIso8601String(),
                    'progress' => $summaries[$assignment->learning_program_id]->toArray(),
                ])->values()->all(),
            ])->all(),
            'programOptions' => LearningProgram::query()
                ->where('status', ProgramStatus::Published)
                ->orderBy('name')
                ->get()
                ->map(fn (LearningProgram $program) => ['id' => $program->public_id, 'name' => $program->name])
                ->all(),
        ]);
    }

    public function update(UpdateCandidateRequest $request, CandidateProfile $candidate, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('update', $candidate);

        DB::transaction(function () use ($request, $candidate, $logger): void {
            $candidate->user->fill($request->safe()->only(['name', 'email']));
            $candidate->fill($request->safe()->only(['phone', 'last_education']));

            $changed = array_keys([...$candidate->user->getDirty(), ...$candidate->getDirty()]);

            $candidate->user->save();
            $candidate->save();

            if ($changed !== []) {
                $logger->log('candidate.updated', $candidate, ['fields' => $changed]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Data kandidat berhasil diperbarui.')]);

        return to_route('admin.candidates.show', $candidate);
    }

    public function updateStatus(CandidateStatusRequest $request, CandidateProfile $candidate, SetCandidateActive $setActive): RedirectResponse
    {
        $this->authorize('update', $candidate);

        $active = $request->boolean('active');
        $setActive($candidate, $active, $request->validated('reason'), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $active ? __('Akun kandidat diaktifkan kembali.') : __('Akun kandidat dinonaktifkan.'),
        ]);

        return back();
    }

    /**
     * Tautan aktivasi ditampilkan sekali lewat flash dan tidak disimpan di halaman.
     */
    public function activationLink(CandidateProfile $candidate, CreateActivationLink $createLink): RedirectResponse
    {
        $this->authorize('update', $candidate);

        if ($candidate->activated_at !== null || ! $candidate->user->is_active) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Tautan aktivasi hanya untuk kandidat aktif yang belum mengaktifkan akun.')]);

            return back();
        }

        Inertia::flash('activationLink', $createLink($candidate->user));

        return back();
    }

    /**
     * @param  Builder<RecruitmentCampaign>  $query
     * @return array<int, array{id: string, name: string, status: string}>
     */
    private function campaignOptions(Builder $query): array
    {
        return $query
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (RecruitmentCampaign $campaign) => [
                'id' => $campaign->public_id,
                'name' => $campaign->name,
                'status' => $campaign->status->value,
            ])
            ->values()
            ->all();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Candidates\Actions\SaveCampaign;
use App\Domain\Candidates\Actions\SyncCampaignTeam;
use App\Domain\Candidates\Enums\CampaignStatus;
use App\Domain\Candidates\Models\Position;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CampaignRequest;
use App\Http\Requests\Admin\CampaignTeamRequest;
use App\Http\Resources\Candidates\CampaignResource;
use App\Http\Resources\Candidates\CampaignSummaryResource;
use App\Http\Resources\Paginated;
use App\Models\User;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RecruitmentCampaign::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(CampaignStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $campaigns = RecruitmentCampaign::query()
            ->visibleTo($request->user())
            ->with('position.department')
            ->withCount('applications')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', Search::contains($search)))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/campaigns/index', [
            'campaigns' => Paginated::make($campaigns, CampaignSummaryResource::class),
            'filters' => [
                'search' => $filters['search'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
            'canCreate' => $canCreate = $request->user()->can('create', RecruitmentCampaign::class),
            // Pilihan posisi untuk modal "Buat gelombang".
            'positions' => $canCreate ? $this->positionOptions() : [],
        ]);
    }

    public function store(CampaignRequest $request, SaveCampaign $saveCampaign): RedirectResponse
    {
        $this->authorize('create', RecruitmentCampaign::class);

        $campaign = $saveCampaign(null, $request->campaignData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Gelombang berhasil dibuat.')]);

        return to_route('admin.campaigns.show', $campaign);
    }

    public function show(Request $request, RecruitmentCampaign $campaign): Response
    {
        $this->authorize('view', $campaign);

        $campaign->load(['position.department', 'hrUsers', 'creator']);

        $canManageTeam = $request->user()->can('manageTeam', $campaign);
        $canUpdate = $request->user()->can('update', $campaign);

        return Inertia::render('admin/campaigns/show', [
            'campaign' => CampaignResource::make($campaign)->resolve($request),
            'stageCounts' => $campaign->applications()
                ->selectRaw('stage, count(*) as total')
                ->groupBy('stage')
                ->pluck('total', 'stage'),
            'canUpdate' => $canUpdate,
            // Pilihan posisi untuk modal "Ubah gelombang".
            'positions' => $canUpdate ? $this->positionOptions($campaign->position) : [],
            'canManageTeam' => $canManageTeam,
            'hrOptions' => $canManageTeam
                ? CampaignTeamRequest::assignableHr()->orderBy('name')->get()->map(fn (User $user) => [
                    'id' => $user->public_id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])->all()
                : [],
        ]);
    }

    public function update(CampaignRequest $request, RecruitmentCampaign $campaign, SaveCampaign $saveCampaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        $saveCampaign($campaign, $request->campaignData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Gelombang berhasil diperbarui.')]);

        return to_route('admin.campaigns.show', $campaign);
    }

    public function updateTeam(CampaignTeamRequest $request, RecruitmentCampaign $campaign, SyncCampaignTeam $syncTeam): RedirectResponse
    {
        $this->authorize('manageTeam', $campaign);

        $syncTeam($campaign, $request->hrUserIds(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tim HR berhasil diperbarui.')]);

        return back();
    }

    /**
     * Posisi aktif, ditambah posisi yang sedang dipakai gelombang (meski sudah nonaktif).
     *
     * @return array<int, array{id: string, name: string, department: string}>
     */
    private function positionOptions(?Position $current = null): array
    {
        return Position::query()
            ->with('department')
            ->where(fn ($query) => $query->where('is_active', true)->when($current, fn ($query) => $query->orWhereKey($current->id)))
            ->orderBy('name')
            ->get()
            ->map(fn (Position $position) => [
                'id' => $position->public_id,
                'name' => $position->name,
                'department' => $position->department->name,
            ])
            ->values()
            ->all();
    }
}

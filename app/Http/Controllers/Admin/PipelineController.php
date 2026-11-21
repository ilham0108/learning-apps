<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Candidates\Actions\ManagePipeline;
use App\Domain\Candidates\Models\CampaignStage;
use App\Domain\Candidates\Models\PipelineStageTemplate;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Alur tahap rekrutmen: alur default (hak `pipeline.manage_default`) dan alur
 * per rekrutmen (hak `pipeline.manage_campaign`, dalam lingkup rekrutmen).
 */
class PipelineController extends Controller
{
    public function __construct(private readonly ManagePipeline $pipeline) {}

    public function index(): Response
    {
        return Inertia::render('admin/pipeline/index', [
            'stages' => PipelineStageTemplate::query()->orderBy('position')->get()
                ->map(fn (PipelineStageTemplate $stage) => ['id' => $stage->public_id, 'name' => $stage->name])->all(),
            'maxStages' => ManagePipeline::MAX_STAGES,
        ]);
    }

    public function storeDefault(Request $request): RedirectResponse
    {
        $this->pipeline->add(null, $this->name($request), $request->user());

        return $this->saved(__('Tahap ditambahkan ke alur default.'));
    }

    public function updateDefault(Request $request, PipelineStageTemplate $stage): RedirectResponse
    {
        $this->pipeline->rename(null, $stage, $this->name($request), $request->user());

        return $this->saved(__('Nama tahap diperbarui.'));
    }

    public function moveDefault(Request $request, PipelineStageTemplate $stage): RedirectResponse
    {
        $this->pipeline->move(null, $stage, $this->direction($request), $request->user());

        return back();
    }

    public function destroyDefault(Request $request, PipelineStageTemplate $stage): RedirectResponse
    {
        $this->pipeline->remove(null, $stage, $request->user());

        return $this->saved(__('Tahap dihapus dari alur default.'));
    }

    public function storeCampaign(Request $request, RecruitmentCampaign $campaign): RedirectResponse
    {
        $this->authorize('managePipeline', $campaign);
        $this->pipeline->add($campaign, $this->name($request), $request->user());

        return $this->saved(__('Tahap ditambahkan.'));
    }

    public function updateCampaign(Request $request, RecruitmentCampaign $campaign, CampaignStage $stage): RedirectResponse
    {
        $this->authorizeStage($campaign, $stage);
        $this->pipeline->rename($campaign, $stage, $this->name($request), $request->user());

        return $this->saved(__('Nama tahap diperbarui.'));
    }

    public function moveCampaign(Request $request, RecruitmentCampaign $campaign, CampaignStage $stage): RedirectResponse
    {
        $this->authorizeStage($campaign, $stage);
        $this->pipeline->move($campaign, $stage, $this->direction($request), $request->user());

        return back();
    }

    public function destroyCampaign(Request $request, RecruitmentCampaign $campaign, CampaignStage $stage): RedirectResponse
    {
        $this->authorizeStage($campaign, $stage);
        $this->pipeline->remove($campaign, $stage, $request->user());

        return $this->saved(__('Tahap dihapus.'));
    }

    private function authorizeStage(RecruitmentCampaign $campaign, CampaignStage $stage): void
    {
        $this->authorize('managePipeline', $campaign);
        abort_unless($stage->recruitment_campaign_id === $campaign->id, 404);
    }

    private function name(Request $request): string
    {
        return trim($request->validate(['name' => ['required', 'string', 'max:100']], attributes: ['name' => 'nama tahap'])['name']);
    }

    private function direction(Request $request): string
    {
        return $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}

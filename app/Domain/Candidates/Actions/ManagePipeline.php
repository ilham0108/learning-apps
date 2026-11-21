<?php

namespace App\Domain\Candidates\Actions;

use App\Domain\Candidates\Models\CampaignStage;
use App\Domain\Candidates\Models\PipelineStageTemplate;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menambah, mengganti nama, mengurutkan, dan menghapus tahap proses, baik di
 * alur default (`$campaign` null) maupun di alur satu rekrutmen. Tahap yang
 * pernah dipakai lamaran tidak dapat dihapus agar riwayat tetap utuh.
 */
class ManagePipeline
{
    public const MAX_STAGES = 15;

    public function __construct(private readonly ActivityLogger $logger) {}

    public function add(?RecruitmentCampaign $campaign, string $name, User $actor): void
    {
        DB::transaction(function () use ($campaign, $name, $actor): void {
            $query = $this->query($campaign);

            if ((clone $query)->count() >= self::MAX_STAGES) {
                throw ValidationException::withMessages(['name' => __('Maksimal :count tahap proses.', ['count' => self::MAX_STAGES])]);
            }

            $this->ensureUniqueName($campaign, $name, null);
            $position = (int) (clone $query)->max('position') + 1;

            $campaign === null
                ? PipelineStageTemplate::query()->create(['name' => $name, 'position' => $position])
                : $campaign->stages()->create(['name' => $name, 'position' => $position]);

            $this->log($campaign, 'added', ['name' => $name], $actor);
        });
    }

    public function rename(?RecruitmentCampaign $campaign, PipelineStageTemplate|CampaignStage $stage, string $name, User $actor): void
    {
        $this->ensureUniqueName($campaign, $name, $stage);
        $previous = $stage->name;
        $stage->update(['name' => $name]);

        $this->log($campaign, 'renamed', ['from' => $previous, 'to' => $name], $actor);
    }

    /**
     * Menukar urutan dengan tahap di atas (`up`) atau di bawahnya (`down`).
     */
    public function move(?RecruitmentCampaign $campaign, PipelineStageTemplate|CampaignStage $stage, string $direction, User $actor): void
    {
        DB::transaction(function () use ($campaign, $stage, $direction, $actor): void {
            $neighbour = $this->query($campaign)
                ->where('position', $direction === 'up' ? '<' : '>', $stage->position)
                ->orderBy('position', $direction === 'up' ? 'desc' : 'asc')
                ->first();

            if ($neighbour === null) {
                return;
            }

            [$a, $b] = [$stage->position, $neighbour->position];
            $stage->update(['position' => $b]);
            $neighbour->update(['position' => $a]);

            $this->log($campaign, 'reordered', ['name' => $stage->name, 'direction' => $direction], $actor);
        });
    }

    public function remove(?RecruitmentCampaign $campaign, PipelineStageTemplate|CampaignStage $stage, User $actor): void
    {
        if ($stage instanceof CampaignStage && $stage->applications()->exists()) {
            throw ValidationException::withMessages(['stage' => __('Tahap ":name" sudah dipakai kandidat sehingga tidak dapat dihapus. Ganti namanya jika perlu.', ['name' => $stage->name])]);
        }

        DB::transaction(function () use ($campaign, $stage, $actor): void {
            $name = $stage->name;
            $stage->delete();

            // Rapatkan urutan agar tetap 1, 2, 3, ...
            foreach ($this->query($campaign)->orderBy('position')->get()->values() as $index => $item) {
                $item->update(['position' => $index + 1]);
            }

            $this->log($campaign, 'removed', ['name' => $name], $actor);
        });
    }

    /**
     * @return Builder<PipelineStageTemplate>|Builder<CampaignStage>
     */
    private function query(?RecruitmentCampaign $campaign): Builder
    {
        return $campaign === null
            ? PipelineStageTemplate::query()
            : CampaignStage::query()->where('recruitment_campaign_id', $campaign->id);
    }

    private function ensureUniqueName(?RecruitmentCampaign $campaign, string $name, ?Model $except): void
    {
        $exists = $this->query($campaign)
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => __('Tahap dengan nama ini sudah ada di alur.')]);
        }
    }

    /**
     * @param  array<string, string>  $properties
     */
    private function log(?RecruitmentCampaign $campaign, string $change, array $properties, User $actor): void
    {
        $this->logger->log(
            $campaign === null ? "pipeline.default_{$change}" : "pipeline.campaign_{$change}",
            $campaign,
            $properties,
            actor: $actor,
        );
    }
}

<?php

namespace App\Domain\Candidates\Actions;

use App\Domain\Candidates\Enums\CampaignStatus;
use App\Domain\Candidates\Models\PipelineStageTemplate;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Membuat atau mengubah rekrutmen. HR yang membuat rekrutmen otomatis ditugaskan
 * ke rekrutmen tersebut (keputusan K2). Perubahan status dicatat di audit log.
 */
class SaveCampaign
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array{name: string, position_id: int, starts_on: string, ends_on: string|null, status: CampaignStatus}  $data
     */
    public function __invoke(?RecruitmentCampaign $campaign, array $data, User $actor): RecruitmentCampaign
    {
        return DB::transaction(function () use ($campaign, $data, $actor) {
            $isNew = $campaign === null;
            $campaign ??= new RecruitmentCampaign(['created_by' => $actor->id]);
            $previousStatus = $campaign->status;

            $campaign->fill([
                ...$data,
                'closed_at' => match (true) {
                    $data['status'] !== CampaignStatus::Closed => null,
                    $previousStatus === CampaignStatus::Closed => $campaign->closed_at,
                    default => now(),
                },
            ])->save();

            // Rekrutmen baru memakai salinan alur default; perubahan default berikutnya tidak memengaruhinya.
            if ($isNew) {
                PipelineStageTemplate::query()->orderBy('position')->get()->each(
                    fn (PipelineStageTemplate $template) => $campaign->stages()->create(['name' => $template->name, 'position' => $template->position]),
                );
            }

            if ($isNew && $actor->isHr()) {
                $campaign->hrUsers()->attach($actor->id, ['assigned_by' => $actor->id]);
            }

            if ($isNew) {
                $this->logger->log('campaign.created', $campaign, actor: $actor);
            } elseif ($previousStatus !== $campaign->status) {
                $this->logger->log('campaign.status_changed', $campaign, [
                    'from' => $previousStatus->value,
                    'to' => $campaign->status->value,
                ], actor: $actor);
            }

            return $campaign;
        });
    }
}

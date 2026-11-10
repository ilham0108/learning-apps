<?php

namespace App\Domain\Candidates\Actions;

use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Menetapkan HR yang ditugaskan ke gelombang (khusus Super Admin). Mengubah hak akses,
 * sehingga selalu dicatat di audit log.
 */
class SyncCampaignTeam
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<int, int>  $hrUserIds
     */
    public function __invoke(RecruitmentCampaign $campaign, array $hrUserIds, User $actor): void
    {
        DB::transaction(function () use ($campaign, $hrUserIds, $actor): void {
            $changes = $campaign->hrUsers()->syncWithPivotValues($hrUserIds, ['assigned_by' => $actor->id], detaching: true);

            if ($changes['attached'] === [] && $changes['detached'] === []) {
                return;
            }

            $publicIds = fn (array $ids) => User::query()->whereKey($ids)->pluck('public_id')->all();

            $this->logger->log('campaign.team_changed', $campaign, [
                'added' => $publicIds($changes['attached']),
                'removed' => $publicIds($changes['detached']),
            ], actor: $actor);
        });
    }
}

<?php

namespace App\Domain\Candidates\Policies;

use App\Domain\Access\Enums\Permission;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Staf melihat rekrutmen dalam lingkupnya (yang ditugaskan, atau semua jika
 * memiliki hak `campaigns.view_all`). Tindakan diatur hak jabatan.
 */
class RecruitmentCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isCandidate();
    }

    public function view(User $user, RecruitmentCampaign $campaign): Response
    {
        return $this->inScope($user, $campaign);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CampaignsManage);
    }

    public function update(User $user, RecruitmentCampaign $campaign): Response
    {
        return $this->inScopeWith($user, $campaign, Permission::CampaignsManage);
    }

    public function manageTeam(User $user, RecruitmentCampaign $campaign): Response
    {
        return $this->inScopeWith($user, $campaign, Permission::CampaignsAssignTeam);
    }

    public function managePipeline(User $user, RecruitmentCampaign $campaign): Response
    {
        return $this->inScopeWith($user, $campaign, Permission::PipelineManageCampaign);
    }

    private function inScopeWith(User $user, RecruitmentCampaign $campaign, Permission $permission): Response
    {
        if (! $this->inScope($user, $campaign)->allowed()) {
            return Response::denyAsNotFound();
        }

        return $user->hasPermission($permission) ? Response::allow() : Response::deny();
    }

    private function inScope(User $user, RecruitmentCampaign $campaign): Response
    {
        return RecruitmentCampaign::query()->visibleTo($user)->whereKey($campaign->getKey())->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}

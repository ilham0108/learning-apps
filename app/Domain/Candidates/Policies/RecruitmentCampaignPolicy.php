<?php

namespace App\Domain\Candidates\Policies;

use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Data di luar lingkup gelombang dikembalikan sebagai 404, bukan 403,
 * agar keberadaan data milik HR lain tidak terungkap.
 */
class RecruitmentCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isHr();
    }

    public function view(User $user, RecruitmentCampaign $campaign): Response
    {
        return $this->inScope($user, $campaign);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isHr();
    }

    public function update(User $user, RecruitmentCampaign $campaign): Response
    {
        return $this->inScope($user, $campaign);
    }

    /**
     * Menugaskan atau melepas HR dari gelombang hanya oleh Super Admin.
     */
    public function manageTeam(User $user, RecruitmentCampaign $campaign): Response
    {
        if (! $this->inScope($user, $campaign)->allowed()) {
            return Response::denyAsNotFound();
        }

        return $user->isSuperAdmin() ? Response::allow() : Response::deny();
    }

    private function inScope(User $user, RecruitmentCampaign $campaign): Response
    {
        return RecruitmentCampaign::query()->visibleTo($user)->whereKey($campaign->getKey())->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}

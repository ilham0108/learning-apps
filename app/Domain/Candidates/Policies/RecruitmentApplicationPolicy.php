<?php

namespace App\Domain\Candidates\Policies;

use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RecruitmentApplicationPolicy
{
    public function view(User $user, RecruitmentApplication $application): Response
    {
        return $this->inScope($user, $application);
    }

    /**
     * Mengubah tahap rekrutmen, menulis catatan internal, dan mengisi evaluasi.
     */
    public function manage(User $user, RecruitmentApplication $application): Response
    {
        if (! $this->inScope($user, $application)->allowed()) {
            return Response::denyAsNotFound();
        }

        return $user->isCandidate() ? Response::deny() : Response::allow();
    }

    private function inScope(User $user, RecruitmentApplication $application): Response
    {
        return RecruitmentApplication::query()->visibleTo($user)->whereKey($application->getKey())->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}

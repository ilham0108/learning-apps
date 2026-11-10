<?php

namespace App\Domain\Candidates\Policies;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CandidateProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isHr();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isHr();
    }

    public function view(User $user, CandidateProfile $profile): Response
    {
        return $this->inScope($user, $profile);
    }

    /**
     * Mengubah data kandidat oleh HR/Super Admin. Kandidat mengubah profilnya
     * lewat halaman profil sendiri dengan field terbatas.
     */
    public function update(User $user, CandidateProfile $profile): Response
    {
        if ($user->isCandidate()) {
            return $profile->user_id === $user->id ? Response::deny() : Response::denyAsNotFound();
        }

        return $this->inScope($user, $profile);
    }

    private function inScope(User $user, CandidateProfile $profile): Response
    {
        return CandidateProfile::query()->visibleTo($user)->whereKey($profile->getKey())->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}

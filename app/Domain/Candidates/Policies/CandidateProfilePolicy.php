<?php

namespace App\Domain\Candidates\Policies;

use App\Domain\Access\Enums\Permission;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Semua staf boleh melihat kandidat dalam lingkup rekrutmennya. Menambah dan
 * mengubah data kandidat butuh hak `candidates.manage`.
 */
class CandidateProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isCandidate();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CandidatesManage);
    }

    public function view(User $user, CandidateProfile $profile): Response
    {
        return $this->inScope($user, $profile);
    }

    /**
     * Mengubah data kandidat oleh staf. Kandidat mengubah profilnya sendiri
     * lewat halaman Data diri dengan field terbatas.
     */
    public function update(User $user, CandidateProfile $profile): Response
    {
        if ($user->isCandidate()) {
            return $profile->user_id === $user->id ? Response::deny() : Response::denyAsNotFound();
        }

        if (! $this->inScope($user, $profile)->allowed()) {
            return Response::denyAsNotFound();
        }

        return $user->hasPermission(Permission::CandidatesManage) ? Response::allow() : Response::deny();
    }

    private function inScope(User $user, CandidateProfile $profile): Response
    {
        return CandidateProfile::query()->visibleTo($user)->whereKey($profile->getKey())->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}

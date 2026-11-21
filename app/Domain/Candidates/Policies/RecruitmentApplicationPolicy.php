<?php

namespace App\Domain\Candidates\Policies;

use App\Domain\Access\Enums\Permission;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Lamaran di luar lingkup → 404. Di dalam lingkup, tiap tindakan butuh hak
 * jabatan masing-masing (ubah tahap, catatan internal, evaluasi).
 */
class RecruitmentApplicationPolicy
{
    public function view(User $user, RecruitmentApplication $application): Response
    {
        return $this->inScope($user, $application);
    }

    public function changeStage(User $user, RecruitmentApplication $application): Response
    {
        return $this->staffWith($user, $application, Permission::ApplicationsMoveStage);
    }

    public function writeNote(User $user, RecruitmentApplication $application): Response
    {
        return $this->staffWith($user, $application, Permission::NotesWrite);
    }

    public function evaluate(User $user, RecruitmentApplication $application): Response
    {
        return $this->staffWith($user, $application, Permission::EvaluationsWrite);
    }

    private function staffWith(User $user, RecruitmentApplication $application, Permission $permission): Response
    {
        if ($user->isCandidate() || ! $this->inScope($user, $application)->allowed()) {
            return Response::denyAsNotFound();
        }

        return $user->hasPermission($permission) ? Response::allow() : Response::deny();
    }

    private function inScope(User $user, RecruitmentApplication $application): Response
    {
        return RecruitmentApplication::query()->visibleTo($user)->whereKey($application->getKey())->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}

<?php

namespace App\Domain\Learning\Policies;

use App\Domain\Access\Enums\Permission;
use App\Domain\Learning\Enums\ProgramStatus;
use App\Domain\Learning\Models\LearningProgram;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Program bersifat bersama untuk staf yang memiliki hak pembelajaran.
 * Kandidat hanya melihat program terbit yang ditugaskan kepadanya.
 */
class LearningProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::LearningManage) || $user->hasPermission(Permission::LearningAssign);
    }

    public function view(User $user, LearningProgram $program): Response
    {
        if (! $user->isCandidate()) {
            return $this->viewAny($user) ? Response::allow() : Response::denyAsNotFound();
        }

        return LearningProgram::query()->assignedToCandidate($user)->whereKey($program->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::LearningManage);
    }

    public function update(User $user, LearningProgram $program): Response
    {
        return $user->hasPermission(Permission::LearningManage) ? Response::allow() : Response::denyAsNotFound();
    }

    public function assign(User $user, LearningProgram $program): Response
    {
        if (! $user->hasPermission(Permission::LearningAssign)) {
            return Response::denyAsNotFound();
        }

        return $program->status === ProgramStatus::Published
            ? Response::allow()
            : Response::deny(__('Hanya program yang sudah terbit yang dapat ditugaskan.'));
    }

    /**
     * Mencabut penugasan program dari kandidat.
     */
    public function unassign(User $user, LearningProgram $program): Response
    {
        return $user->hasPermission(Permission::LearningAssign) ? Response::allow() : Response::denyAsNotFound();
    }
}

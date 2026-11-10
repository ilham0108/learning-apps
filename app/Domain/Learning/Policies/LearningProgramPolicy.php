<?php

namespace App\Domain\Learning\Policies;

use App\Domain\Learning\Enums\ProgramStatus;
use App\Domain\Learning\Models\LearningProgram;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Program bersifat bersama untuk semua HR (rules Bagian 4). Kandidat hanya melihat
 * program terbit yang ditugaskan kepadanya; selain itu 404.
 */
class LearningProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isCandidate();
    }

    public function view(User $user, LearningProgram $program): Response
    {
        if (! $user->isCandidate()) {
            return Response::allow();
        }

        return LearningProgram::query()->assignedToCandidate($user)->whereKey($program->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return ! $user->isCandidate();
    }

    public function update(User $user, LearningProgram $program): Response
    {
        return $user->isCandidate() ? Response::denyAsNotFound() : Response::allow();
    }

    public function assign(User $user, LearningProgram $program): Response
    {
        if ($user->isCandidate()) {
            return Response::denyAsNotFound();
        }

        return $program->status === ProgramStatus::Published
            ? Response::allow()
            : Response::deny(__('Hanya program yang sudah terbit yang dapat ditugaskan.'));
    }
}

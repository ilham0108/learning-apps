<?php

namespace App\Domain\Learning\Policies;

use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\LearningProgram;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Materi dan file materi hanya boleh dibuka staf atau kandidat yang ditugaskan
 * program terbit yang memuatnya. Materi yang diarsipkan tidak terlihat oleh kandidat.
 */
class LearningMaterialPolicy
{
    public function view(User $user, LearningMaterial $material): Response
    {
        if (! $user->isCandidate()) {
            return Response::allow();
        }

        if ($material->archived_at !== null) {
            return Response::denyAsNotFound();
        }

        return LearningProgram::query()
            ->assignedToCandidate($user)
            ->whereKey($material->module->learning_program_id)
            ->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Menandai selesai hanya oleh kandidat yang berhak membuka materi.
     */
    public function complete(User $user, LearningMaterial $material): Response
    {
        if (! $user->isCandidate()) {
            return Response::deny();
        }

        return $this->view($user, $material);
    }

    public function update(User $user, LearningMaterial $material): Response
    {
        return $user->isCandidate() ? Response::denyAsNotFound() : Response::allow();
    }
}

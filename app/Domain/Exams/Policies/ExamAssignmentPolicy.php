<?php

namespace App\Domain\Exams\Policies;

use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Exams\Models\ExamAssignment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ExamAssignmentPolicy
{
    /**
     * Melihat instruksi dan memulai ujian: hanya kandidat pemilik penugasan.
     */
    public function take(User $user, ExamAssignment $assignment): Response
    {
        if (! $user->isCandidate()) {
            return Response::denyAsNotFound();
        }

        return RecruitmentApplication::query()->visibleTo($user)->whereKey($assignment->recruitment_application_id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}

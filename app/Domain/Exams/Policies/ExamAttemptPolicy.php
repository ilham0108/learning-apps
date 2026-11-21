<?php

namespace App\Domain\Exams\Policies;

use App\Domain\Exams\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Kandidat hanya mengakses attempt miliknya; HR hanya attempt dalam rekrutmen
 * yang ditugaskan kepadanya. Di luar lingkup → 404 (rules Bagian 13).
 */
class ExamAttemptPolicy
{
    public function view(User $user, ExamAttempt $attempt): Response
    {
        return $this->inScope($user, $attempt);
    }

    /**
     * Mengerjakan attempt: hanya kandidat pemiliknya.
     */
    public function take(User $user, ExamAttempt $attempt): Response
    {
        return $user->isCandidate() ? $this->inScope($user, $attempt) : Response::denyAsNotFound();
    }

    /**
     * Melihat detail, log peristiwa, dan menilai: Super Admin dan HR dalam lingkup.
     */
    public function grade(User $user, ExamAttempt $attempt): Response
    {
        return $user->isCandidate() ? Response::denyAsNotFound() : $this->inScope($user, $attempt);
    }

    private function inScope(User $user, ExamAttempt $attempt): Response
    {
        return ExamAttempt::query()->visibleTo($user)->whereKey($attempt->getKey())->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}

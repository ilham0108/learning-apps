<?php

namespace App\Domain\Exams\Policies;

use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Models\Exam;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Ujian bersifat bersama untuk semua HR (rules Bagian 4); penugasan dibatasi
 * lingkup gelombang lewat validasi target. Kandidat mengakses ujian lewat
 * penugasan miliknya (Tahap 7), bukan lewat halaman administrasi.
 */
class ExamPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isCandidate();
    }

    public function view(User $user, Exam $exam): Response
    {
        return $user->isCandidate() ? Response::denyAsNotFound() : Response::allow();
    }

    public function create(User $user): bool
    {
        return ! $user->isCandidate();
    }

    public function update(User $user, Exam $exam): Response
    {
        return $this->view($user, $exam);
    }

    /**
     * Soal dan bobot hanya dapat diubah sebelum ada attempt.
     */
    public function editQuestions(User $user, Exam $exam): Response
    {
        if ($user->isCandidate()) {
            return Response::denyAsNotFound();
        }

        return $exam->isLocked()
            ? Response::deny(__('Soal dan bobot ujian yang sudah dikerjakan tidak dapat diubah.'))
            : Response::allow();
    }

    public function assign(User $user, Exam $exam): Response
    {
        if ($user->isCandidate()) {
            return Response::denyAsNotFound();
        }

        return $exam->status === ExamStatus::Published
            ? Response::allow()
            : Response::deny(__('Hanya ujian yang sudah terbit yang dapat ditugaskan.'));
    }
}

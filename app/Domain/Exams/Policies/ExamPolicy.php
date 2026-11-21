<?php

namespace App\Domain\Exams\Policies;

use App\Domain\Access\Enums\Permission;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Models\Exam;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Ujian bersifat bersama untuk staf yang memiliki hak ujian; penugasan dibatasi
 * lingkup rekrutmen lewat validasi target. Kandidat mengakses ujian lewat
 * penugasan miliknya, bukan lewat halaman administrasi.
 */
class ExamPolicy
{
    private const VIEW_PERMISSIONS = [
        Permission::ExamsManage, Permission::ExamsAssign, Permission::GradingGrade, Permission::ResultsRelease,
    ];

    public function viewAny(User $user): bool
    {
        foreach (self::VIEW_PERMISSIONS as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function view(User $user, Exam $exam): Response
    {
        return $this->viewAny($user) ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ExamsManage);
    }

    public function update(User $user, Exam $exam): Response
    {
        return $this->with($user, Permission::ExamsManage);
    }

    /**
     * Soal dan bobot hanya dapat diubah sebelum ada attempt.
     */
    public function editQuestions(User $user, Exam $exam): Response
    {
        if (! $this->with($user, Permission::ExamsManage)->allowed()) {
            return Response::denyAsNotFound();
        }

        return $exam->isLocked()
            ? Response::deny(__('Soal dan bobot ujian yang sudah dikerjakan tidak dapat diubah.'))
            : Response::allow();
    }

    public function releaseResults(User $user, Exam $exam): Response
    {
        return $this->with($user, Permission::ResultsRelease);
    }

    public function assign(User $user, Exam $exam): Response
    {
        if (! $this->with($user, Permission::ExamsAssign)->allowed()) {
            return Response::denyAsNotFound();
        }

        return $exam->status === ExamStatus::Published
            ? Response::allow()
            : Response::deny(__('Hanya ujian yang sudah terbit yang dapat ditugaskan.'));
    }

    /**
     * Mengubah jadwal khusus atau mencabut penugasan ujian.
     */
    public function manageAssignments(User $user, Exam $exam): Response
    {
        return $this->with($user, Permission::ExamsAssign);
    }

    private function with(User $user, Permission $permission): Response
    {
        if (! $this->viewAny($user)) {
            return Response::denyAsNotFound();
        }

        return $user->hasPermission($permission) ? Response::allow() : Response::deny();
    }
}

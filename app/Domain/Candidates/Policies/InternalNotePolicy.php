<?php

namespace App\Domain\Candidates\Policies;

use App\Domain\Candidates\Models\InternalNote;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Catatan internal: HR dalam lingkup boleh membaca dan menulis, tetapi hanya
 * penulis (atau Super Admin) yang boleh mengubah atau menghapusnya.
 */
class InternalNotePolicy
{
    public function update(User $user, InternalNote $note): Response
    {
        if (! RecruitmentApplication::query()->visibleTo($user)->whereKey($note->recruitment_application_id)->exists()) {
            return Response::denyAsNotFound();
        }

        return $user->isSuperAdmin() || ($user->isHr() && $note->author_id === $user->id)
            ? Response::allow()
            : Response::deny();
    }

    public function delete(User $user, InternalNote $note): Response
    {
        return $this->update($user, $note);
    }
}

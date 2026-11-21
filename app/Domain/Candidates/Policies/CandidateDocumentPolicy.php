<?php

namespace App\Domain\Candidates\Policies;

use App\Domain\Candidates\Models\CandidateDocument;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Dokumen mengikuti lingkup profil kandidat: kandidat pemiliknya, HR yang
 * kandidatnya melamar di rekrutmen yang ditugaskan, dan Super Admin.
 * Hanya kandidat pemilik yang dapat mengganti atau menghapus dokumennya.
 */
class CandidateDocumentPolicy
{
    public function view(User $user, CandidateDocument $document): Response
    {
        return CandidateProfile::query()->visibleTo($user)->whereKey($document->candidate_profile_id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function delete(User $user, CandidateDocument $document): Response
    {
        return $user->isCandidate() && $document->profile->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}

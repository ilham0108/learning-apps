<?php

namespace App\Domain\Learning\Actions;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\MaterialProgress;

/**
 * Membuka halaman materi mencatat waktu pertama dibuka; selesai hanya lewat
 * tombol "Tandai selesai" (rules Bagian 6). Keduanya idempoten.
 */
class RecordMaterialProgress
{
    public function opened(CandidateProfile $profile, LearningMaterial $material): MaterialProgress
    {
        $progress = MaterialProgress::query()->firstOrCreate(
            ['candidate_profile_id' => $profile->id, 'learning_material_id' => $material->id],
            ['first_opened_at' => now()],
        );

        if ($progress->first_opened_at === null) {
            $progress->update(['first_opened_at' => now()]);
        }

        return $progress;
    }

    public function completed(CandidateProfile $profile, LearningMaterial $material): MaterialProgress
    {
        $progress = $this->opened($profile, $material);

        if ($progress->completed_at === null) {
            $progress->update(['completed_at' => now()]);
        }

        return $progress;
    }
}

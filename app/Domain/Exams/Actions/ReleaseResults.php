<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Exams\Models\Exam;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;

/**
 * Publikasi hasil ujian secara manual oleh HR. Hanya nilai attempt yang sudah
 * final yang terlihat oleh kandidat; attempt yang belum dinilai menyusul
 * setelah penilaiannya selesai.
 */
class ReleaseResults
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function __invoke(Exam $exam, User $actor): void
    {
        if ($exam->results_released_at !== null) {
            return;
        }

        $exam->forceFill(['results_released_at' => now()])->save();
        $this->logger->log('exam.results_released', $exam, actor: $actor);
    }
}

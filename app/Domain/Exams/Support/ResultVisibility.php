<?php

namespace App\Domain\Exams\Support;

use App\Domain\Exams\Enums\ResultRelease;
use App\Domain\Exams\Models\ExamAttempt;

/**
 * Kapan kandidat boleh melihat nilai, kunci, dan pembahasan (Tahap 1 Bagian 10.3).
 * Nilai baru boleh dilihat setelah attempt final; selain itu mengikuti kebijakan
 * publikasi yang di-snapshot di attempt. Publikasi manual oleh HR juga
 * membuka hasil untuk kebijakan "setelah ujian ditutup".
 */
class ResultVisibility
{
    public static function scoreVisible(ExamAttempt $attempt): bool
    {
        if (! $attempt->isGraded()) {
            return false;
        }

        $assignment = $attempt->assignment;
        $released = $assignment->exam->results_released_at !== null;

        return match ($attempt->result_release) {
            ResultRelease::Immediate => true,
            ResultRelease::Manual => $released,
            ResultRelease::AfterClose => $released
                || ($assignment->effectiveClosesAt() !== null && now()->greaterThanOrEqualTo($assignment->effectiveClosesAt())),
        };
    }

    /**
     * Kunci jawaban dan pembahasan hanya dikirim jika nilai boleh dilihat dan ujian mengizinkannya.
     */
    public static function reviewVisible(ExamAttempt $attempt): bool
    {
        return self::scoreVisible($attempt) && $attempt->assignment->exam->show_review;
    }
}

<?php

namespace App\Console\Commands;

use App\Domain\Exams\Actions\SubmitAttempt;
use App\Domain\Exams\Enums\AttemptStatus;
use App\Domain\Exams\Models\ExamAttempt;
use Illuminate\Console\Command;

/**
 * Auto-submit attempt yang sudah melewati deadline. Dijadwalkan setiap menit;
 * pengecekan saat akses di endpoint attempt menjadi cadangannya.
 */
class CloseExpiredAttempts extends Command
{
    protected $signature = 'exams:close-expired';

    protected $description = 'Kumpulkan otomatis attempt ujian yang sudah melewati batas waktu';

    public function handle(SubmitAttempt $submit): int
    {
        $closed = 0;

        ExamAttempt::query()
            ->where('status', AttemptStatus::InProgress)
            ->where('deadline_at', '<=', now())
            ->chunkById(100, function ($attempts) use ($submit, &$closed): void {
                foreach ($attempts as $attempt) {
                    $submit($attempt, auto: true);
                    $closed++;
                }
            });

        $this->info("{$closed} attempt ditutup.");

        return self::SUCCESS;
    }
}

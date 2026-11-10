<?php

namespace App\Domain\Candidates\Actions;

use App\Domain\Candidates\Enums\CampaignStatus;
use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mengubah tahap rekrutmen sesuai diagram transisi (docs/tahap-1.md bagian 10.1),
 * mencatat riwayat (siapa, kapan, dari apa ke apa, alasan) dan audit log.
 */
class ChangeApplicationStage
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function __invoke(RecruitmentApplication $application, RecruitmentStage $target, ?string $reason, User $actor): void
    {
        DB::transaction(function () use ($application, $target, $reason, $actor): void {
            /** @var RecruitmentApplication $locked */
            $locked = RecruitmentApplication::query()->with('campaign')->lockForUpdate()->findOrFail($application->id);
            $current = $locked->stage;

            if (! $current->canBeChangedManuallyTo($target, $actor->isSuperAdmin())) {
                throw ValidationException::withMessages(['stage' => __('Perubahan tahap ini tidak diizinkan.')]);
            }

            if ($locked->campaign->status === CampaignStatus::Closed && ! $actor->isSuperAdmin()) {
                throw ValidationException::withMessages(['stage' => __('Tahap pada gelombang yang sudah ditutup hanya dapat diubah Super Admin.')]);
            }

            $reason = $reason !== null ? trim($reason) : null;

            if ($current->requiresReason($target) && blank($reason)) {
                throw ValidationException::withMessages(['reason' => __('Alasan wajib diisi untuk perubahan tahap ini.')]);
            }

            $locked->update(['stage' => $target, 'stage_changed_at' => now()]);

            $locked->stageHistories()->create([
                'from_stage' => $current,
                'to_stage' => $target,
                'reason' => $reason ?: null,
                'changed_by' => $actor->id,
            ]);

            $this->logger->log('application.stage_changed', $locked, [
                'from' => $current->value,
                'to' => $target->value,
            ], $reason ?: null, $actor);
        });
    }
}

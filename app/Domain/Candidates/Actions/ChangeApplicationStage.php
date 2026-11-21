<?php

namespace App\Domain\Candidates\Actions;

use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Support\StageTransitions;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Memindahkan lamaran ke tahap berikutnya menurut StageTransitions. Setiap
 * perubahan dicatat di riwayat tahap (dengan nama tahap saat itu) dan audit log.
 */
class ChangeApplicationStage
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function __invoke(RecruitmentApplication $application, string $target, ?string $reason, User $actor): void
    {
        DB::transaction(function () use ($application, $target, $reason, $actor): void {
            /** @var RecruitmentApplication $locked */
            $locked = RecruitmentApplication::query()->lockForUpdate()->findOrFail($application->id);
            $option = StageTransitions::find($locked, $actor, $target);

            if ($option === null) {
                throw ValidationException::withMessages(['target' => __('Perpindahan tahap ini tidak diizinkan. Kandidat hanya dapat maju ke tahap berikutnya secara berurutan.')]);
            }

            $reason = $reason !== null ? trim($reason) : null;

            if ($option['requires_reason'] && blank($reason)) {
                throw ValidationException::withMessages(['reason' => __('Alasan wajib diisi untuk perubahan tahap ini.')]);
            }

            $from = $locked->stage;
            $fromLabel = $locked->stageLabel();

            $locked->update([
                'stage' => $option['stage'],
                // Tahap proses terakhir tetap disimpan saat hasil akhir, agar terlihat di tahap mana keputusan diambil.
                'campaign_stage_id' => $option['campaign_stage']?->id
                    ?? ($option['stage'] === RecruitmentStage::Registered ? null : $locked->campaign_stage_id),
                'stage_changed_at' => now(),
            ]);

            $locked->stageHistories()->create([
                'from_stage' => $from,
                'to_stage' => $option['stage'],
                'from_label' => $fromLabel,
                'to_label' => $option['label'],
                'reason' => $reason ?: null,
                'changed_by' => $actor->id,
            ]);

            $this->logger->log('application.stage_changed', $locked, [
                'from' => $fromLabel,
                'to' => $option['label'],
            ], $reason ?: null, $actor);
        });
    }
}

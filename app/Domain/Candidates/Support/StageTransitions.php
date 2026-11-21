<?php

namespace App\Domain\Candidates\Support;

use App\Domain\Access\Enums\Permission;
use App\Domain\Candidates\Enums\CampaignStatus;
use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\CampaignStage;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Models\User;

/**
 * Aturan perpindahan tahap lamaran (berurutan):
 *
 *   Diundang → Terdaftar (otomatis saat aktivasi akun)
 *   Terdaftar → tahap proses 1 → 2 → … → tahap terakhir → Diterima
 *   Tidak lolos / Mengundurkan diri: kapan saja sebelum hasil akhir, wajib alasan.
 *
 * Kandidat tidak dapat melompati tahap. "Diterima" dan "Tidak lolos" butuh hak
 * `applications.decide`; perpindahan lain butuh `applications.move_stage`.
 * Super Admin dapat mengoreksi "Tidak lolos" kembali ke tahap terakhirnya.
 * Rekrutmen yang ditutup hanya dapat diubah Super Admin.
 *
 * Pilihan yang sama dipakai untuk menampilkan tombol di halaman dan untuk
 * memvalidasi permintaan di server.
 */
class StageTransitions
{
    /**
     * @return array<int, array{target: string, stage: RecruitmentStage, campaign_stage: CampaignStage|null, label: string, requires_reason: bool, kind: string}>
     */
    public static function options(RecruitmentApplication $application, User $actor): array
    {
        $application->loadMissing(['campaign.stages', 'campaignStage']);

        if ($application->campaign->status === CampaignStatus::Closed && ! $actor->isSuperAdmin()) {
            return [];
        }

        $stages = $application->campaign->stages->values();
        $options = [];
        $canMove = $actor->hasPermission(Permission::ApplicationsMoveStage);
        $canDecide = $actor->hasPermission(Permission::ApplicationsDecide);

        $next = match ($application->stage) {
            RecruitmentStage::Registered => $stages->first(),
            RecruitmentStage::InProcess => $stages->first(fn (CampaignStage $stage) => $stage->position > ($application->campaignStage->position ?? 0)),
            default => null,
        };
        $canAdvance = in_array($application->stage, [RecruitmentStage::Registered, RecruitmentStage::InProcess], true);

        if ($canAdvance && $next !== null && $canMove) {
            $options[] = self::option("stage:{$next->public_id}", RecruitmentStage::InProcess, $next, $next->name, false, 'advance');
        }

        // Diterima hanya setelah melewati tahap proses terakhir (atau jika rekrutmen tidak punya tahap proses).
        if ($canAdvance && $next === null && $canDecide) {
            $options[] = self::option('hired', RecruitmentStage::Hired, null, RecruitmentStage::Hired->label(), false, 'decision');
        }

        if ($canAdvance && $canDecide) {
            $options[] = self::option('rejected', RecruitmentStage::Rejected, null, RecruitmentStage::Rejected->label(), true, 'decision');
        }

        if (! $application->stage->isFinal() && $canMove) {
            $options[] = self::option('withdrawn', RecruitmentStage::Withdrawn, null, RecruitmentStage::Withdrawn->label(), true, 'exit');
        }

        // Koreksi oleh Super Admin: kembalikan kandidat yang tidak lolos ke tahap terakhirnya.
        if ($application->stage === RecruitmentStage::Rejected && $actor->isSuperAdmin()) {
            $back = $application->campaignStage;
            $options[] = $back !== null
                ? self::option("stage:{$back->public_id}", RecruitmentStage::InProcess, $back, $back->name, true, 'correction')
                : self::option('registered', RecruitmentStage::Registered, null, RecruitmentStage::Registered->label(), true, 'correction');
        }

        return $options;
    }

    /**
     * @return array{target: string, stage: RecruitmentStage, campaign_stage: CampaignStage|null, label: string, requires_reason: bool, kind: string}|null
     */
    public static function find(RecruitmentApplication $application, User $actor, string $target): ?array
    {
        foreach (self::options($application, $actor) as $option) {
            if ($option['target'] === $target) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @return array{target: string, stage: RecruitmentStage, campaign_stage: CampaignStage|null, label: string, requires_reason: bool, kind: string}
     */
    private static function option(string $target, RecruitmentStage $stage, ?CampaignStage $campaignStage, string $label, bool $requiresReason, string $kind): array
    {
        return [
            'target' => $target,
            'stage' => $stage,
            'campaign_stage' => $campaignStage,
            'label' => $label,
            'requires_reason' => $requiresReason,
            'kind' => $kind,
        ];
    }
}

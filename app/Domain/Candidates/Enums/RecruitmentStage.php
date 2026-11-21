<?php

namespace App\Domain\Candidates\Enums;

/**
 * Kategori tahap lamaran. Tahap proses (misalnya Seleksi berkas, Psikotes,
 * Wawancara) diatur per rekrutmen di tabel `campaign_stages`; lamaran dalam
 * kategori `in_process` menunjuk ke salah satunya. Aturan perpindahan ada di
 * StageTransitions.
 */
enum RecruitmentStage: string
{
    case Invited = 'invited';
    case Registered = 'registered';
    case InProcess = 'in_process';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /**
     * Hasil akhir: tidak ada perpindahan lanjutan (kecuali koreksi Super Admin dari "tidak lolos").
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Hired, self::Rejected, self::Withdrawn], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Invited => __('Diundang'),
            self::Registered => __('Terdaftar'),
            self::InProcess => __('Dalam proses'),
            self::Hired => __('Diterima'),
            self::Rejected => __('Tidak lolos'),
            self::Withdrawn => __('Mengundurkan diri'),
        };
    }
}

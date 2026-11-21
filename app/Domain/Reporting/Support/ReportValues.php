<?php

namespace App\Domain\Reporting\Support;

use Carbon\CarbonImmutable;

/**
 * Format nilai untuk laporan dan file CSV: label status berbahasa Indonesia,
 * waktu di zona tampilan, dan persentase dua desimal.
 */
class ReportValues
{
    public static function stage(?string $stage): string
    {
        return match ($stage) {
            'invited' => __('Diundang'),
            'registered' => __('Terdaftar'),
            'under_review' => __('Ditinjau'),
            'shortlisted' => __('Lolos seleksi awal'),
            'rejected' => __('Tidak lolos'),
            'hired' => __('Diterima'),
            'withdrawn' => __('Mengundurkan diri'),
            default => '',
        };
    }

    public static function result(?string $result): string
    {
        return match ($result) {
            'passed' => __('Lulus'),
            'failed' => __('Tidak lulus'),
            default => '',
        };
    }

    public static function grading(?string $status): string
    {
        return match ($status) {
            'graded' => __('Final'),
            'pending_manual' => __('Menunggu penilaian'),
            default => '',
        };
    }

    public static function learning(int $requiredTotal, int $requiredCompleted, int $touched): string
    {
        $complete = $requiredTotal > 0 ? $requiredCompleted >= $requiredTotal : $touched > 0;

        return match (true) {
            $complete => __('Selesai'),
            $touched > 0 => __('Sedang berjalan'),
            default => __('Belum dimulai'),
        };
    }

    /**
     * Waktu UTC dari database ke zona tampilan, format "2026-11-21 14:05".
     */
    public static function dateTime(?string $value): string
    {
        return $value === null
            ? ''
            : CarbonImmutable::parse($value, 'UTC')->setTimezone((string) config('recruitment.display_timezone'))->format('Y-m-d H:i');
    }

    public static function date(?string $value): string
    {
        return $value === null ? '' : substr($value, 0, 10);
    }

    public static function percent(int|float|string|null $part, int|float|string|null $whole): ?string
    {
        return (float) $whole > 0 ? number_format((float) $part / (float) $whole * 100, 2, '.', '') : null;
    }

    public static function decimal(int|float|string|null $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 2, '.', '');
    }
}

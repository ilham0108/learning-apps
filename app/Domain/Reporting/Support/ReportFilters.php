<?php

namespace App\Domain\Reporting\Support;

use App\Domain\Candidates\Models\Department;
use App\Domain\Candidates\Models\Position;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Exams\Models\Exam;
use App\Domain\Learning\Models\LearningProgram;
use App\Models\User;

/**
 * Filter laporan disimpan dan dikirim sebagai `public_id`, lalu diubah ke ID
 * internal di sini. Rekrutmen di luar lingkup pengguna diabaikan sehingga
 * tidak pernah memperluas akses (filter yang tidak dikenal menghasilkan
 * hasil kosong, bukan data di luar lingkup).
 */
class ReportFilters
{
    public const PERIODS = [7, 30, 90];

    public const KEYS = ['period', 'campaign', 'position', 'department', 'program', 'exam', 'stage'];

    /**
     * @param  array<string, mixed>  $filters  Filter berisi public_id.
     * @return array{period_days: int|null, campaign_id: int|null, position_id: int|null, department_id: int|null, program_id: int|null, exam_id: int|null, stage: string|null}
     */
    public static function resolve(array $filters, User $user): array
    {
        $id = function (string $key, $query) use ($filters): ?int {
            $value = $filters[$key] ?? null;

            if (! is_string($value) || $value === '') {
                return null;
            }

            // ID yang tidak ditemukan memakai 0 agar hasilnya kosong, bukan tanpa filter.
            return (int) ($query->where('public_id', $value)->value('id') ?? 0);
        };

        return [
            'period_days' => in_array((int) ($filters['period'] ?? 0), self::PERIODS, true) ? (int) $filters['period'] : null,
            'campaign_id' => $id('campaign', RecruitmentCampaign::query()->visibleTo($user)),
            'position_id' => $id('position', Position::query()),
            'department_id' => $id('department', Department::query()),
            'program_id' => $id('program', LearningProgram::query()),
            'exam_id' => $id('exam', Exam::query()),
            'stage' => is_string($filters['stage'] ?? null) ? $filters['stage'] : null,
        ];
    }

    /**
     * Hanya kunci filter yang dikenal dan berisi nilai.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int>
     */
    public static function clean(array $filters): array
    {
        return array_filter(
            array_intersect_key($filters, array_flip(self::KEYS)),
            fn ($value) => $value !== null && $value !== '',
        );
    }
}

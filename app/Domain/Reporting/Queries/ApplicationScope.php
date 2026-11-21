<?php

namespace App\Domain\Reporting\Queries;

use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Models\User;
use Illuminate\Database\Query\Builder;

/**
 * ID lamaran dalam lingkup pengguna setelah filter umum diterapkan. Dipakai
 * dashboard HR dan semua laporan, sehingga angka di layar, laporan, dan file
 * ekspor selalu mengikuti lingkup dan filter yang sama.
 */
class ApplicationScope
{
    /**
     * @param  array{period_days?: int|null, campaign_id?: int|null, position_id?: int|null, department_id?: int|null, program_id?: int|null, stage?: string|null}  $filters
     */
    public static function ids(User $user, array $filters): Builder
    {
        return RecruitmentApplication::query()
            ->visibleTo($user)
            ->join('recruitment_campaigns as scope_c', 'scope_c.id', '=', 'recruitment_applications.recruitment_campaign_id')
            ->join('positions as scope_pos', 'scope_pos.id', '=', 'scope_c.position_id')
            ->when($filters['campaign_id'] ?? null, fn ($q, int $id) => $q->where('scope_c.id', $id))
            ->when($filters['position_id'] ?? null, fn ($q, int $id) => $q->where('scope_pos.id', $id))
            ->when($filters['department_id'] ?? null, fn ($q, int $id) => $q->where('scope_pos.department_id', $id))
            ->when($filters['stage'] ?? null, fn ($q, string $stage) => $q->where('recruitment_applications.stage', $stage))
            ->when($filters['period_days'] ?? null, fn ($q, int $days) => $q->where('recruitment_applications.applied_at', '>=', now()->subDays($days)))
            ->when($filters['program_id'] ?? null, fn ($q, int $id) => $q->whereExists(fn ($sub) => $sub
                ->from('program_assignments as scope_pa')
                ->whereColumn('scope_pa.recruitment_application_id', 'recruitment_applications.id')
                ->where('scope_pa.learning_program_id', $id)))
            ->select('recruitment_applications.id')
            ->toBase();
    }
}

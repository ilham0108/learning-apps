<?php

namespace App\Domain\Learning\Actions;

use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Domain\Shared\Enums\AssignmentSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Menugaskan program ke individu, kelompok terpilih, gelombang, atau posisi.
 * Penugasan dimaterialisasi menjadi satu baris per lamaran saat ini juga
 * (keputusan T12); lamaran yang sudah ditugaskan dilewati.
 */
class AssignProgram
{
    /** Tahap yang masih berjalan; lamaran yang sudah selesai tidak diberi program baru. */
    public const ACTIVE_STAGES = [
        RecruitmentStage::Invited,
        RecruitmentStage::Registered,
        RecruitmentStage::UnderReview,
        RecruitmentStage::Shortlisted,
    ];

    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  Builder<RecruitmentApplication>  $targets  Lamaran yang dipilih, sudah dibatasi lingkup pengguna.
     * @return int Jumlah penugasan baru.
     */
    public function __invoke(
        LearningProgram $program,
        Builder $targets,
        AssignmentSource $source,
        ?string $dueAt,
        User $actor,
    ): int {
        return DB::transaction(function () use ($program, $targets, $source, $dueAt, $actor) {
            $applicationIds = $targets
                ->whereIn('stage', self::ACTIVE_STAGES)
                ->whereNotIn('id', $program->assignments()->select('recruitment_application_id'))
                ->pluck('id');

            foreach ($applicationIds as $applicationId) {
                $program->assignments()->create([
                    'recruitment_application_id' => $applicationId,
                    'assigned_via' => $source,
                    'assigned_by' => $actor->id,
                    'due_at' => $dueAt,
                ]);
            }

            if ($applicationIds->isNotEmpty()) {
                $this->logger->log('program.assigned', $program, [
                    'via' => $source->value,
                    'count' => $applicationIds->count(),
                ], actor: $actor);
            }

            return $applicationIds->count();
        });
    }
}

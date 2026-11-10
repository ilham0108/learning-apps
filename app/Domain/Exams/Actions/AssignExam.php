<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Exams\Models\Exam;
use App\Domain\Learning\Actions\AssignProgram;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Domain\Shared\Enums\AssignmentSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Menugaskan ujian ke lamaran yang masih berjalan, satu baris per lamaran.
 * Lamaran yang sudah ditugaskan dilewati.
 */
class AssignExam
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  Builder<RecruitmentApplication>  $targets
     */
    public function __invoke(Exam $exam, Builder $targets, AssignmentSource $source, User $actor): int
    {
        return DB::transaction(function () use ($exam, $targets, $source, $actor) {
            $applicationIds = $targets
                ->whereIn('stage', AssignProgram::ACTIVE_STAGES)
                ->whereNotIn('id', $exam->assignments()->select('recruitment_application_id'))
                ->pluck('id');

            foreach ($applicationIds as $applicationId) {
                $exam->assignments()->create([
                    'recruitment_application_id' => $applicationId,
                    'assigned_via' => $source,
                    'assigned_by' => $actor->id,
                ]);
            }

            if ($applicationIds->isNotEmpty()) {
                $this->logger->log('exam.assigned', $exam, [
                    'via' => $source->value,
                    'count' => $applicationIds->count(),
                ], actor: $actor);
            }

            return $applicationIds->count();
        });
    }
}

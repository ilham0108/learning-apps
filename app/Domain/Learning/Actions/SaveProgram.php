<?php

namespace App\Domain\Learning\Actions;

use App\Domain\Learning\Enums\ProgramStatus;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;

class SaveProgram
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array{name: string, description: string|null, category: string|null, target_position_id: int|null, status: ProgramStatus, estimated_minutes: int|null}  $data
     */
    public function __invoke(?LearningProgram $program, array $data, User $actor): LearningProgram
    {
        $isNew = $program === null;
        $program ??= new LearningProgram(['created_by' => $actor->id]);
        $previousStatus = $program->status;

        $program->fill($data)->save();

        if ($isNew) {
            $this->logger->log('program.created', $program, actor: $actor);
        } elseif ($previousStatus !== $program->status) {
            $this->logger->log('program.status_changed', $program, [
                'from' => $previousStatus->value,
                'to' => $program->status->value,
            ], actor: $actor);
        }

        return $program;
    }
}

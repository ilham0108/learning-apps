<?php

namespace App\Domain\Learning\Queries;

use App\Domain\Learning\Enums\LearningProgressStatus;

final class ProgressSummary
{
    public function __construct(
        public readonly int $requiredTotal,
        public readonly int $requiredCompleted,
        public readonly bool $started,
    ) {}

    public function percent(): int
    {
        if ($this->requiredTotal === 0) {
            return $this->started ? 100 : 0;
        }

        return (int) floor($this->requiredCompleted / $this->requiredTotal * 100);
    }

    public function status(): LearningProgressStatus
    {
        $complete = $this->requiredTotal > 0
            ? $this->requiredCompleted >= $this->requiredTotal
            : $this->started;

        return match (true) {
            $complete => LearningProgressStatus::Completed,
            $this->started => LearningProgressStatus::InProgress,
            default => LearningProgressStatus::NotStarted,
        };
    }

    /**
     * @return array{required_total: int, required_completed: int, percent: int, status: string}
     */
    public function toArray(): array
    {
        return [
            'required_total' => $this->requiredTotal,
            'required_completed' => $this->requiredCompleted,
            'percent' => $this->percent(),
            'status' => $this->status()->value,
        ];
    }
}

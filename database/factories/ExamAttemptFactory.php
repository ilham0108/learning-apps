<?php

namespace Database\Factories;

use App\Domain\Exams\Enums\AttemptResult;
use App\Domain\Exams\Enums\AttemptStatus;
use App\Domain\Exams\Enums\GradingStatus;
use App\Domain\Exams\Enums\NavigationMode;
use App\Domain\Exams\Enums\ResultRelease;
use App\Domain\Exams\Enums\ScorePolicy;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Exams\Models\ExamAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Hanya untuk tes. Attempt sungguhan dibuat oleh mesin ujian (Tahap 7) beserta snapshot-nya.
 *
 * @extends Factory<ExamAttempt>
 */
class ExamAttemptFactory extends Factory
{
    protected $model = ExamAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_assignment_id' => ExamAssignment::factory(),
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'deadline_at' => now()->addHour(),
            'max_points' => 100,
            'passing_grade' => 70,
            'navigation' => NavigationMode::Free,
            'score_policy' => ScorePolicy::Highest,
            'result_release' => ResultRelease::Immediate,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttemptStatus::Submitted,
            'submitted_at' => now(),
            'grading_status' => GradingStatus::PendingManual,
        ]);
    }

    public function graded(float $scorePercent = 80): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttemptStatus::Submitted,
            'submitted_at' => now(),
            'finalized_at' => now(),
            'grading_status' => GradingStatus::Graded,
            'earned_points' => $scorePercent,
            'score_percent' => $scorePercent,
            'result' => $scorePercent >= 70 ? AttemptResult::Passed : AttemptResult::Failed,
        ]);
    }
}

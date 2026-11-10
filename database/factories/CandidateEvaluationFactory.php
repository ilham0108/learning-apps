<?php

namespace Database\Factories;

use App\Domain\Candidates\Enums\EvaluationCriterion;
use App\Domain\Candidates\Enums\EvaluationStatus;
use App\Domain\Candidates\Enums\Recommendation;
use App\Domain\Candidates\Models\CandidateEvaluation;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateEvaluation>
 */
class CandidateEvaluationFactory extends Factory
{
    protected $model = CandidateEvaluation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recruitment_application_id' => RecruitmentApplication::factory(),
            'evaluator_id' => User::factory()->hr(),
            'recommendation' => null,
            'summary' => null,
            'status' => EvaluationStatus::Draft,
        ];
    }

    /**
     * Evaluasi final lengkap dengan skor untuk setiap kriteria.
     */
    public function finalized(Recommendation $recommendation = Recommendation::Yes): static
    {
        return $this->state(fn (array $attributes) => [
            'recommendation' => $recommendation,
            'summary' => fake()->sentence(12),
            'status' => EvaluationStatus::Final,
            'finalized_at' => now(),
        ])->afterCreating(function (CandidateEvaluation $evaluation): void {
            foreach (EvaluationCriterion::cases() as $criterion) {
                $evaluation->scores()->create([
                    'criterion' => $criterion,
                    'score' => fake()->numberBetween(2, 5),
                    'note' => fake()->sentence(),
                ]);
            }
        });
    }
}

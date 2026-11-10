<?php

namespace Database\Factories;

use App\Domain\Candidates\Models\InternalNote;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalNote>
 */
class InternalNoteFactory extends Factory
{
    protected $model = InternalNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recruitment_application_id' => RecruitmentApplication::factory(),
            'author_id' => User::factory()->hr(),
            'body' => fake()->paragraph(),
        ];
    }
}

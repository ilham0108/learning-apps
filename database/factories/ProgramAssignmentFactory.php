<?php

namespace Database\Factories;

use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Models\ProgramAssignment;
use App\Domain\Shared\Enums\AssignmentSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramAssignment>
 */
class ProgramAssignmentFactory extends Factory
{
    protected $model = ProgramAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_program_id' => LearningProgram::factory()->published(),
            'recruitment_application_id' => RecruitmentApplication::factory(),
            'assigned_via' => AssignmentSource::Individual,
            'assigned_by' => null,
            'due_at' => null,
        ];
    }
}

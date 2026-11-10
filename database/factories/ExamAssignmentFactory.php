<?php

namespace Database\Factories;

use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Shared\Enums\AssignmentSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAssignment>
 */
class ExamAssignmentFactory extends Factory
{
    protected $model = ExamAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory()->published(),
            'recruitment_application_id' => RecruitmentApplication::factory(),
            'assigned_via' => AssignmentSource::Individual,
            'assigned_by' => null,
        ];
    }
}

<?php

namespace App\Http\Resources\Exams;

use App\Domain\Exams\Models\Exam;
use App\Domain\Learning\Models\LearningProgram;
use App\Support\LocalDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Konfigurasi ujian untuk HR. Waktu dikirim dua kali: ISO 8601 UTC untuk tampilan
 * dan format input lokal untuk form.
 *
 * @mixin Exam
 */
class ExamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $program = fn (LearningProgram $program) => ['id' => $program->public_id, 'name' => $program->name];

        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type->value,
            'duration_minutes' => $this->duration_minutes,
            'passing_grade' => $this->passing_grade,
            'max_attempts' => $this->max_attempts,
            'opens_at' => $this->opens_at?->toIso8601String(),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'opens_at_input' => LocalDateTime::toInput($this->opens_at),
            'closes_at_input' => LocalDateTime::toInput($this->closes_at),
            'shuffle_questions' => $this->shuffle_questions,
            'shuffle_options' => $this->shuffle_options,
            'navigation' => $this->navigation->value,
            'score_policy' => $this->score_policy->value,
            'multi_select_scoring' => $this->multi_select_scoring->value,
            'result_release' => $this->result_release->value,
            'show_review' => $this->show_review,
            'status' => $this->status->value,
            'is_locked' => $this->isLocked(),
            'duplicated_from' => $this->duplicatedFrom === null ? null : [
                'id' => $this->duplicatedFrom->public_id,
                'title' => $this->duplicatedFrom->title,
            ],
            'prerequisite_programs' => $this->whenLoaded('prerequisitePrograms', fn () => $this->prerequisitePrograms->map($program)->values()->all()),
            'related_programs' => $this->whenLoaded('programs', fn () => $this->programs->map($program)->values()->all()),
            'questions_count' => $this->whenCounted('examQuestions'),
            'assignments_count' => $this->whenCounted('assignments'),
            // Hanya ada jika query memakai withSum('examQuestions as total_points').
            'total_points' => $this->when(
                array_key_exists('total_points', $this->resource->getAttributes()),
                fn () => number_format((float) $this->resource->getAttribute('total_points'), 2, '.', ''),
            ),
        ];
    }
}

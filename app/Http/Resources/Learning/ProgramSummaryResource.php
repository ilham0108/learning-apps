<?php

namespace App\Http\Resources\Learning;

use App\Domain\Learning\Models\LearningProgram;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LearningProgram
 */
class ProgramSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'category' => $this->category,
            'status' => $this->status->value,
            'target_position' => $this->targetPosition?->name,
            'estimated_minutes' => $this->estimated_minutes,
            'modules_count' => $this->whenCounted('modules'),
            'materials_count' => $this->whenCounted('materials'),
            'assignments_count' => $this->whenCounted('assignments'),
        ];
    }
}

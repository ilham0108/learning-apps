<?php

namespace App\Http\Resources\Candidates;

use App\Domain\Candidates\Models\Department;
use App\Domain\Candidates\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Department
 */
class DepartmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'positions' => $this->whenLoaded('positions', fn () => $this->positions->map(fn (Position $position) => [
                'id' => $position->public_id,
                'name' => $position->name,
                'is_active' => $position->is_active,
                'campaigns_count' => $position->campaigns_count ?? 0,
            ])->values()->all()),
        ];
    }
}

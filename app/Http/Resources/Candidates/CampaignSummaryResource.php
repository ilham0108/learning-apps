<?php

namespace App\Http\Resources\Candidates;

use App\Domain\Candidates\Models\RecruitmentCampaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecruitmentCampaign
 */
class CampaignSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'status' => $this->status->value,
            'position' => $this->position->name,
            'department' => $this->position->department->name,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'applications_count' => $this->whenCounted('applications'),
        ];
    }
}

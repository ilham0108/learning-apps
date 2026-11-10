<?php

namespace App\Http\Resources\Candidates;

use App\Domain\Candidates\Models\RecruitmentApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lamaran dari sudut pandang kandidat sendiri: tanpa catatan internal atau evaluasi HR.
 *
 * @mixin RecruitmentApplication
 */
class OwnApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'campaign' => $this->campaign->name,
            'position' => $this->campaign->position->name,
            'department' => $this->campaign->position->department->name,
            'stage' => $this->stage->value,
            'applied_at' => $this->applied_at->toIso8601String(),
        ];
    }
}

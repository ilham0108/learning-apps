<?php

namespace App\Http\Resources\Candidates;

use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail gelombang untuk halaman detail dan form ubah.
 *
 * @mixin RecruitmentCampaign
 */
class CampaignResource extends JsonResource
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
            'position' => [
                'id' => $this->position->public_id,
                'name' => $this->position->name,
                'department' => $this->position->department->name,
            ],
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_by' => $this->creator?->name,
            'hr_users' => $this->whenLoaded('hrUsers', fn () => $this->hrUsers->map(fn (User $user) => [
                'id' => $user->public_id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values()->all()),
        ];
    }
}

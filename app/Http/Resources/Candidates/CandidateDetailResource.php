<?php

namespace App\Http\Resources\Candidates;

use App\Domain\Candidates\Models\CandidateProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail kandidat untuk HR. Lamaran yang dimuat hanya yang berada dalam lingkup HR.
 *
 * @mixin CandidateProfile
 */
class CandidateDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'candidate_number' => $this->candidate_number,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'phone' => $this->phone,
            'last_education' => $this->last_education,
            'registered_on' => $this->registered_on->toDateString(),
            'is_active' => $this->user->is_active,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'consent_given_at' => $this->consent_given_at?->toIso8601String(),
            'consent_policy_version' => $this->consent_policy_version,
            'last_login_at' => $this->user->last_login_at?->toIso8601String(),
            'applications' => ApplicationDetailResource::collection($this->applications)->resolve($request),
        ];
    }
}

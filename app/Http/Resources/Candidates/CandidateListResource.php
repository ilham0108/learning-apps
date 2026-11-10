<?php

namespace App\Http\Resources\Candidates;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Baris tabel kandidat. `applications` hanya berisi lamaran dalam lingkup pengguna
 * (relasi dimuat dengan scope visibleTo di controller).
 *
 * @mixin CandidateProfile
 */
class CandidateListResource extends JsonResource
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
            'is_active' => $this->user->is_active,
            'is_activated' => $this->activated_at !== null,
            'registered_on' => $this->registered_on->toDateString(),
            'applications' => $this->applications->map(fn (RecruitmentApplication $application) => [
                'id' => $application->public_id,
                'campaign' => $application->campaign->name,
                'position' => $application->campaign->position->name,
                'stage' => $application->stage->value,
            ])->values()->all(),
        ];
    }
}

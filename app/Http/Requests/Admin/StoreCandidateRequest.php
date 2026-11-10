<?php

namespace App\Http\Requests\Admin;

use App\Domain\Candidates\Enums\CampaignStatus;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Http\Rules\PublicIdIn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class StoreCandidateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...CandidateProfileRules::rules(),
            'email' => ['required', 'string', 'email', 'max:255'],
            'campaign' => ['required', new PublicIdIn(self::openCampaigns($this))],
        ];
    }

    /**
     * Gelombang yang boleh menerima kandidat: dalam lingkup pengguna dan belum ditutup.
     *
     * @return Builder<RecruitmentCampaign>
     */
    public static function openCampaigns(Request $request): Builder
    {
        return RecruitmentCampaign::query()
            ->visibleTo($request->user())
            ->where('status', '!=', CampaignStatus::Closed);
    }

    /**
     * @return array{name: string, email: string, phone: string|null, last_education: string|null}
     */
    public function candidateData(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'email' => $this->string('email')->trim()->toString(),
            'phone' => $this->filled('phone') ? $this->string('phone')->trim()->toString() : null,
            'last_education' => $this->filled('last_education') ? $this->string('last_education')->trim()->toString() : null,
        ];
    }

    public function campaign(): RecruitmentCampaign
    {
        return self::openCampaigns($this)->where('public_id', $this->string('campaign')->toString())->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...CandidateProfileRules::attributes(), 'campaign' => 'gelombang'];
    }
}

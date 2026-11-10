<?php

namespace App\Http\Requests\Admin;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCandidateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var CandidateProfile $candidate */
        $candidate = $this->route('candidate');

        return [
            ...CandidateProfileRules::rules(),
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($candidate->user_id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return CandidateProfileRules::attributes();
    }
}

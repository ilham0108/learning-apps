<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * Email kandidat adalah identitas lamaran dan hanya diubah oleh HR.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->user()->isCandidate()) {
            return [
                'name' => $this->nameRules(),
                'email' => ['prohibited'],
            ];
        }

        return $this->profileRules($this->user()->id);
    }
}

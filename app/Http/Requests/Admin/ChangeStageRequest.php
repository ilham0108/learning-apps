<?php

namespace App\Http\Requests\Admin;

use App\Domain\Candidates\Enums\RecruitmentStage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStageRequest extends FormRequest
{
    /**
     * Aturan transisi dan kewajiban alasan diperiksa di ChangeApplicationStage.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::enum(RecruitmentStage::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['stage' => 'tahap', 'reason' => 'alasan'];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            // 'stage:<public_id>' untuk tahap proses, atau 'hired', 'rejected', 'withdrawn', 'registered'.
            'target' => ['required', 'string', 'max:40'],
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

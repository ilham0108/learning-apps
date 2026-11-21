<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Batas atas poin (poin maksimal soal) dan kewajiban alasan setelah final
 * diperiksa di GradeAnswer karena bergantung pada snapshot attempt.
 */
class GradeAnswerRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'points' => ['required', 'numeric', 'min:0', 'max:9999', 'decimal:0,2'],
            'note' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['points' => 'poin', 'note' => 'catatan', 'reason' => 'alasan'];
    }
}

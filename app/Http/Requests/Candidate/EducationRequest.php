<?php

namespace App\Http\Requests\Candidate;

use App\Domain\Candidates\Enums\EducationLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EducationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'level' => ['required', Rule::enum(EducationLevel::class)],
            'institution' => ['required', 'string', 'max:150'],
            'major' => ['nullable', 'string', 'max:150'],
            'start_year' => ['nullable', 'integer', 'between:1950,'.now()->year],
            // Kosong berarti masih berjalan; boleh tahun rencana lulus.
            // Dibandingkan dengan tahun masuk hanya jika tahun masuk diisi.
            'graduation_year' => ['nullable', 'integer', 'between:1950,'.(now()->year + 6), Rule::when($this->filled('start_year'), 'gte:start_year')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'level' => 'jenjang',
            'institution' => 'nama institusi',
            'major' => 'jurusan',
            'start_year' => 'tahun masuk',
            'graduation_year' => 'tahun lulus',
        ];
    }
}

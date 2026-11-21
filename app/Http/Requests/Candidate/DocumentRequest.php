<?php

namespace App\Http\Requests\Candidate;

use App\Domain\Candidates\Enums\CandidateDocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Unggahan dokumen. Tipe file diperiksa dari isi file (bukan hanya ekstensi)
 * dan dibatasi 5 MB.
 */
class DocumentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = CandidateDocumentType::tryFrom((string) $this->input('type'));

        return [
            'type' => ['required', Rule::enum(CandidateDocumentType::class)],
            'file' => ['required', ...($type?->fileRules() ?? ['file'])],
            'label' => [$type === CandidateDocumentType::Certificate ? 'required' : 'nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['type' => 'jenis dokumen', 'file' => 'file', 'label' => 'nama sertifikat'];
    }
}

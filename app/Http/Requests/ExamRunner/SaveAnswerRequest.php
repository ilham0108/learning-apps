<?php

namespace App\Http\Requests\ExamRunner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Autosave satu jawaban. Pilihan dikirim sebagai token per attempt; kecocokan
 * token dengan soal diperiksa di SaveAnswer.
 */
class SaveAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:1', 'max:4000000000'],
            'selected' => ['nullable', 'array', 'max:20'],
            'selected.*' => ['string', 'size:26'],
            'text' => ['nullable', 'string', 'max:20000'],
            'flagged' => ['nullable', 'boolean'],
        ];
    }
}

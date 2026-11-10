<?php

namespace App\Http\Requests\Admin;

use App\Domain\Candidates\Enums\EvaluationCriterion;
use App\Domain\Candidates\Enums\Recommendation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EvaluationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $criteria = array_map(fn (EvaluationCriterion $criterion) => $criterion->value, EvaluationCriterion::cases());

        return [
            'scores' => ['required', 'array:'.implode(',', $criteria)],
            'scores.*.score' => ['nullable', 'integer', 'between:1,5'],
            'scores.*.note' => ['nullable', 'string', 'max:2000'],
            'recommendation' => ['nullable', Rule::enum(Recommendation::class)],
            'summary' => ['nullable', 'string', 'max:5000'],
            'finalize' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, array{score: int|null, note: string|null}>
     */
    public function scores(): array
    {
        /** @var array<string, array{score?: int|string|null, note?: string|null}> $scores */
        $scores = $this->validated('scores');

        return array_map(fn (array $entry) => [
            'score' => isset($entry['score']) ? (int) $entry['score'] : null,
            'note' => $entry['note'] ?? null,
        ], $scores);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'scores.*.score' => 'skor',
            'scores.*.note' => 'catatan kriteria',
            'recommendation' => 'rekomendasi',
            'summary' => 'ringkasan',
        ];
    }
}

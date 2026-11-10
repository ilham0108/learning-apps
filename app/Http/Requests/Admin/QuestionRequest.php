<?php

namespace App\Http\Requests\Admin;

use App\Domain\QuestionBank\Enums\Difficulty;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Models\QuestionCategory;
use App\Http\Rules\PublicIdIn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi bentuk data soal. Aturan isi per jenis (jumlah jawaban benar, jawaban
 * diterima, sub-soal) diperiksa di SaveQuestion setelah HTML disanitasi.
 */
class QuestionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isCase = $this->enum('type', QuestionType::class) === QuestionType::CaseStudy;

        return [
            'category' => ['required', new PublicIdIn(QuestionCategory::query())],
            'topic' => ['nullable', 'string', 'max:150'],
            ...$this->itemRules('', allowCase: true),
            'default_points' => $isCase ? ['nullable'] : ['required', 'numeric', 'between:0.01,1000'],
            'children' => [Rule::requiredIf($isCase), 'array', 'max:20'],
            ...$this->itemRules('children.*.', allowCase: false),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function itemRules(string $prefix, bool $allowCase): array
    {
        $types = $allowCase ? QuestionType::cases() : array_filter(QuestionType::cases(), fn (QuestionType $type) => ! $type->isContainer());

        return [
            $prefix.'type' => ['required', Rule::in(array_map(fn (QuestionType $type) => $type->value, $types))],
            $prefix.'difficulty' => ['required', Rule::enum(Difficulty::class)],
            $prefix.'body_html' => ['required', 'string', 'max:100000'],
            $prefix.'explanation_html' => ['nullable', 'string', 'max:50000'],
            $prefix.'rubric' => ['nullable', 'string', 'max:5000'],
            ...($prefix === '' ? [] : [$prefix.'default_points' => ['required', 'numeric', 'between:0.01,1000']]),
            $prefix.'options' => ['nullable', 'array', 'max:10'],
            $prefix.'options.*.body' => ['nullable', 'string', 'max:1000'],
            $prefix.'options.*.is_correct' => ['required', 'boolean'],
            $prefix.'accepted_answers' => ['nullable', 'array', 'max:20'],
            $prefix.'accepted_answers.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function questionData(): array
    {
        return [
            'question_category_id' => (int) QuestionCategory::query()->where('public_id', $this->string('category')->toString())->value('id'),
            'topic' => $this->filled('topic') ? $this->string('topic')->trim()->toString() : null,
            ...$this->itemData($this->all()),
            'children' => array_map(fn (array $child) => $this->itemData($child), array_values((array) $this->input('children', []))),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function itemData(array $item): array
    {
        return [
            'type' => QuestionType::from((string) $item['type']),
            'difficulty' => Difficulty::from((string) $item['difficulty']),
            'body_html' => (string) $item['body_html'],
            'explanation_html' => isset($item['explanation_html']) ? (string) $item['explanation_html'] : null,
            'rubric' => isset($item['rubric']) && trim((string) $item['rubric']) !== '' ? trim((string) $item['rubric']) : null,
            'default_points' => number_format((float) ($item['default_points'] ?? 0), 2, '.', ''),
            'options' => array_map(fn (array $option) => [
                'body' => (string) ($option['body'] ?? ''),
                'is_correct' => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOL),
            ], array_values((array) ($item['options'] ?? []))),
            'accepted_answers' => array_map(fn ($answer) => (string) $answer, array_values((array) ($item['accepted_answers'] ?? []))),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category' => 'kategori',
            'topic' => 'topik',
            'difficulty' => 'tingkat kesulitan',
            'type' => 'jenis soal',
            'body_html' => 'pertanyaan',
            'explanation_html' => 'penjelasan jawaban',
            'rubric' => 'rubrik',
            'default_points' => 'bobot',
            'children' => 'sub-soal',
            'children.*.body_html' => 'pertanyaan sub-soal',
            'children.*.default_points' => 'bobot sub-soal',
        ];
    }
}

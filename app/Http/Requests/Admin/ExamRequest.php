<?php

namespace App\Http\Requests\Admin;

use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Enums\ExamType;
use App\Domain\Exams\Enums\MultiSelectScoring;
use App\Domain\Exams\Enums\NavigationMode;
use App\Domain\Exams\Enums\ResultRelease;
use App\Domain\Exams\Enums\ScorePolicy;
use App\Domain\Learning\Models\LearningProgram;
use App\Http\Rules\PublicIdIn;
use App\Support\LocalDateTime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Konfigurasi ujian (rules Bagian 8). Waktu buka/tutup diisi dalam zona tampilan
 * (Asia/Jakarta) dan disimpan dalam UTC.
 */
class ExamRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $dateTime = 'date_format:'.LocalDateTime::INPUT_FORMAT;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::enum(ExamType::class)],
            'duration_minutes' => ['required', 'integer', 'between:1,600'],
            'passing_grade' => ['required', 'numeric', 'between:0,100'],
            'max_attempts' => ['required', 'integer', 'between:1,10'],
            'opens_at' => ['nullable', $dateTime],
            'closes_at' => ['nullable', $dateTime, 'after:opens_at'],
            'shuffle_questions' => ['required', 'boolean'],
            'shuffle_options' => ['required', 'boolean'],
            'navigation' => ['required', Rule::enum(NavigationMode::class)],
            'score_policy' => ['required', Rule::enum(ScorePolicy::class)],
            'multi_select_scoring' => ['required', Rule::enum(MultiSelectScoring::class)],
            'result_release' => ['required', Rule::enum(ResultRelease::class)],
            'show_review' => ['required', 'boolean'],
            'status' => ['required', Rule::enum(ExamStatus::class)],
            'prerequisite_programs' => ['present', 'array'],
            'prerequisite_programs.*' => ['distinct', new PublicIdIn(LearningProgram::query())],
            'related_programs' => ['present', 'array'],
            'related_programs.*' => ['distinct', new PublicIdIn(LearningProgram::query())],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function examData(): array
    {
        return [
            'title' => $this->string('title')->trim()->toString(),
            'description' => $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            'type' => $this->enum('type', ExamType::class),
            'duration_minutes' => $this->integer('duration_minutes'),
            'passing_grade' => number_format($this->float('passing_grade'), 2, '.', ''),
            'max_attempts' => $this->integer('max_attempts'),
            'opens_at' => LocalDateTime::toUtc($this->input('opens_at')),
            'closes_at' => LocalDateTime::toUtc($this->input('closes_at')),
            'shuffle_questions' => $this->boolean('shuffle_questions'),
            'shuffle_options' => $this->boolean('shuffle_options'),
            'navigation' => $this->enum('navigation', NavigationMode::class),
            'score_policy' => $this->enum('score_policy', ScorePolicy::class),
            'multi_select_scoring' => $this->enum('multi_select_scoring', MultiSelectScoring::class),
            'result_release' => $this->enum('result_release', ResultRelease::class),
            'show_review' => $this->boolean('show_review'),
            'status' => $this->enum('status', ExamStatus::class),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function programIds(string $key): array
    {
        /** @var list<string> $publicIds */
        $publicIds = (array) $this->input($key, []);

        return LearningProgram::query()
            ->whereIn('public_id', $publicIds)
            ->get()
            ->map(fn (LearningProgram $program) => $program->id)
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'judul ujian',
            'duration_minutes' => 'durasi',
            'passing_grade' => 'nilai kelulusan',
            'max_attempts' => 'jumlah percobaan',
            'opens_at' => 'waktu buka',
            'closes_at' => 'waktu tutup',
            'prerequisite_programs.*' => 'program prasyarat',
            'related_programs.*' => 'program terkait',
        ];
    }
}

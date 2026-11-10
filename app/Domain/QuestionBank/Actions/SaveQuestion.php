<?php

namespace App\Domain\QuestionBank\Actions;

use App\Domain\Exams\Models\ExamQuestion;
use App\Domain\QuestionBank\Enums\QuestionStatus;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Domain\Shared\Support\HtmlSanitizer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menyimpan soal beserta pilihan, jawaban diterima, dan sub-soal studi kasus.
 *
 * Versioning copy-on-write (docs/tahap-1.md bagian 6.3): soal yang dipakai ujian
 * terkunci tidak diubah di tempat. Perubahan membuat baris versi baru; ujian terkunci
 * tetap memakai versi lama, ujian yang belum terkunci dialihkan ke versi baru.
 */
class SaveQuestion
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Hasil QuestionRequest::questionData().
     */
    public function __invoke(?Question $question, array $data, User $actor): Question
    {
        $data = $this->normalize($data);
        $this->validate($data);

        return DB::transaction(function () use ($question, $data, $actor) {
            if ($question === null) {
                $created = $this->createTree($data, $actor, version: 1, previous: null);
                $this->logger->log('question.created', $created, actor: $actor);

                return $created;
            }

            /** @var Question $locked */
            $locked = Question::query()->lockForUpdate()->findOrFail($question->id);

            if ($locked->superseded_at !== null) {
                throw ValidationException::withMessages(['question' => __('Soal ini sudah memiliki versi yang lebih baru.')]);
            }

            if (self::isUsedByLockedExam($locked)) {
                return $this->createNewVersion($locked, $data, $actor);
            }

            $this->updateInPlace($locked, $data);
            $this->logger->log('question.updated', $locked, actor: $actor);

            return $locked;
        });
    }

    public static function isUsedByLockedExam(Question $question): bool
    {
        return ExamQuestion::query()
            ->where('question_id', $question->id)
            ->whereHas('exam', fn ($query) => $query->whereNotNull('locked_at'))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createNewVersion(Question $old, array $data, User $actor): Question
    {
        $new = $this->createTree($data, $actor, version: $old->version + 1, previous: $old);

        $old->forceFill(['superseded_at' => now()])->save();
        $old->children()->update(['superseded_at' => now()]);

        // Ujian yang belum terkunci ikut memakai versi terbaru.
        ExamQuestion::query()
            ->where('question_id', $old->id)
            ->whereHas('exam', fn ($query) => $query->whereNull('locked_at'))
            ->update(['question_id' => $new->id, 'points' => $this->examPoints($new)]);

        $this->logger->log('question.versioned', $new, [
            'previous' => $old->public_id,
            'version' => $new->version,
        ], actor: $actor);

        return $new;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createTree(array $data, User $actor, int $version, ?Question $previous): Question
    {
        $question = Question::query()->create([
            ...$this->questionAttributes($data),
            'question_category_id' => $data['question_category_id'],
            'topic' => $data['topic'],
            'version' => $version,
            'previous_version_id' => $previous?->id,
            'status' => $previous->status ?? QuestionStatus::Active,
            'created_by' => $actor->id,
        ]);

        $this->syncContent($question, $data);

        return $question;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateInPlace(Question $question, array $data): void
    {
        $question->update([
            ...$this->questionAttributes($data),
            'question_category_id' => $data['question_category_id'],
            'topic' => $data['topic'],
        ]);

        $question->options()->delete();
        $question->acceptedAnswers()->delete();
        $question->children()->each(fn (Question $child) => $child->delete());

        $this->syncContent($question, $data);

        ExamQuestion::query()->where('question_id', $question->id)->update(['points' => $this->examPoints($question)]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncContent(Question $question, array $data): void
    {
        $this->syncAnswers($question, $data);

        foreach ($data['children'] as $position => $child) {
            $childQuestion = $question->children()->create([
                ...$this->questionAttributes($child),
                'question_category_id' => $question->question_category_id,
                'topic' => $question->topic,
                'version' => $question->version,
                'position' => $position + 1,
                'status' => $question->status,
                'created_by' => $question->created_by,
            ]);

            $this->syncAnswers($childQuestion, $child);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncAnswers(Question $question, array $data): void
    {
        foreach ($data['options'] as $position => $option) {
            $question->options()->create([
                'body_html' => $option['body'],
                'is_correct' => $option['is_correct'],
                'position' => $position + 1,
            ]);
        }

        foreach ($data['accepted_answers'] as $answer) {
            $question->acceptedAnswers()->create(['answer' => $answer]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function questionAttributes(array $data): array
    {
        return [
            'type' => $data['type'],
            'difficulty' => $data['difficulty'],
            'body_html' => $data['body_html'],
            'explanation_html' => $data['explanation_html'],
            'rubric' => $data['type'] === QuestionType::Essay ? $data['rubric'] : null,
            'default_points' => $data['type']->isContainer() ? 0 : $data['default_points'],
        ];
    }

    /**
     * Bobot di ujian: bobot default soal, atau jumlah bobot sub-soal untuk studi kasus.
     */
    public function examPoints(Question $question): string
    {
        if (! $question->type->isContainer()) {
            return $question->default_points;
        }

        return number_format((float) $question->children()->sum('default_points'), 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, bool $isChild = false): array
    {
        $type = $data['type'];

        $data['body_html'] = $this->sanitizer->clean($data['body_html']);
        $data['explanation_html'] = $this->sanitizer->hasVisibleText($data['explanation_html'] ?? null)
            ? $this->sanitizer->clean($data['explanation_html'])
            : null;

        // Pilihan jawaban disimpan sebagai teks biasa dan selalu ditampilkan sebagai teks.
        $data['options'] = $type->hasOptions()
            ? array_values(array_map(fn (array $option) => [
                'body' => trim(strip_tags($option['body'])),
                'is_correct' => (bool) $option['is_correct'],
            ], $data['options'] ?? []))
            : [];

        if ($type === QuestionType::TrueFalse) {
            $correctIsTrue = (bool) ($data['options'][0]['is_correct'] ?? true);
            $data['options'] = [
                ['body' => __('Benar'), 'is_correct' => $correctIsTrue],
                ['body' => __('Salah'), 'is_correct' => ! $correctIsTrue],
            ];
        }

        $data['accepted_answers'] = $type === QuestionType::ShortAnswer
            ? array_values(array_filter(array_map(fn (string $answer) => trim($answer), $data['accepted_answers'] ?? [])))
            : [];

        $data['children'] = ! $isChild && $type->isContainer()
            ? array_map(fn (array $child) => $this->normalize($child, true), $data['children'] ?? [])
            : [];

        return $data;
    }

    /**
     * Aturan isi per jenis soal (rules Bagian 7).
     *
     * @param  array<string, mixed>  $data
     */
    private function validate(array $data, string $prefix = ''): void
    {
        $errors = [];
        $type = $data['type'];

        if (! $this->sanitizer->hasVisibleText($data['body_html'])) {
            $errors[$prefix.'body_html'] = __('Pertanyaan wajib diisi.');
        }

        $options = $data['options'];
        $correct = count(array_filter($options, fn (array $option) => $option['is_correct']));

        if (in_array($type, [QuestionType::MultipleChoice, QuestionType::MultipleSelect], true)) {
            if (count($options) < 2 || in_array('', array_column($options, 'body'), true)) {
                $errors[$prefix.'options'] = __('Isi minimal dua pilihan jawaban.');
            } elseif ($type === QuestionType::MultipleChoice && $correct !== 1) {
                $errors[$prefix.'options'] = __('Pilihan ganda harus memiliki tepat satu jawaban benar.');
            } elseif ($type === QuestionType::MultipleSelect && $correct < 1) {
                $errors[$prefix.'options'] = __('Tandai minimal satu jawaban benar.');
            }
        }

        if ($type === QuestionType::ShortAnswer) {
            $normalized = array_map(fn (string $answer) => mb_strtolower((string) preg_replace('/\s+/u', ' ', $answer)), $data['accepted_answers']);

            if ($normalized === []) {
                $errors[$prefix.'accepted_answers'] = __('Isi minimal satu jawaban yang diterima.');
            } elseif (count($normalized) !== count(array_unique($normalized))) {
                $errors[$prefix.'accepted_answers'] = __('Jawaban yang diterima tidak boleh sama.');
            }
        }

        if ($type->isContainer()) {
            if ($data['children'] === []) {
                $errors[$prefix.'children'] = __('Studi kasus harus memiliki minimal satu sub-soal.');
            }

            foreach ($data['children'] as $index => $child) {
                if ($child['type']->isContainer()) {
                    $errors["children.{$index}.type"] = __('Sub-soal tidak boleh berupa studi kasus.');

                    continue;
                }

                try {
                    $this->validate($child, "children.{$index}.");
                } catch (ValidationException $exception) {
                    $errors = [...$errors, ...$exception->errors()];
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}

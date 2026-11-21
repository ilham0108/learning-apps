<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Exams\Enums\NavigationMode;
use App\Domain\Exams\Exceptions\AttemptRejected;
use App\Domain\Exams\Models\AttemptAnswer;
use App\Domain\Exams\Models\AttemptQuestion;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\QuestionBank\Enums\QuestionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Autosave satu jawaban (satu baris per soal). Baris attempt dikunci agar
 * penyimpanan tidak berbarengan dengan submit, dan penyimpanan ditolak setelah
 * `deadline_at` menurut jam server (risiko R3).
 *
 * `client_revision` naik setiap kali kandidat mengubah jawaban; server hanya
 * menerima revisi yang lebih baru, sehingga antrean kirim ulang di klien tidak
 * menimpa jawaban yang lebih baru (aturan B13).
 */
class SaveAnswer
{
    /**
     * @param  array{revision: int, selected?: array<int, string>|null, text?: string|null, flagged?: bool|null}  $data
     */
    public function __invoke(ExamAttempt $attempt, string $questionToken, array $data): AttemptAnswer
    {
        return DB::transaction(function () use ($attempt, $questionToken, $data) {
            /** @var ExamAttempt $locked */
            $locked = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if (! $locked->isInProgress() || $locked->isPastDeadline()) {
                throw AttemptRejected::finished();
            }

            /** @var AttemptQuestion $question */
            $question = AttemptQuestion::query()
                ->where('exam_attempt_id', $locked->id)
                ->where('token', $questionToken)
                ->where('type', '!=', QuestionType::CaseStudy->value)
                ->with(['options', 'parent'])
                ->firstOrFail();

            $itemPosition = $question->parent->position ?? $question->position;

            if ($locked->navigation === NavigationMode::ForwardOnly && $itemPosition < $locked->furthest_position) {
                throw AttemptRejected::questionLocked();
            }

            $answer = AttemptAnswer::query()->firstOrNew([
                'exam_attempt_id' => $locked->id,
                'attempt_question_id' => $question->id,
            ]);

            if ($answer->exists && $answer->client_revision >= $data['revision']) {
                return $answer;
            }

            $optionIds = $this->optionIds($question, $data['selected'] ?? []);

            $answer->fill([
                'text_answer' => $question->type->hasOptions() ? null : $this->text($data['text'] ?? null),
                'is_flagged' => (bool) ($data['flagged'] ?? false),
                'client_revision' => $data['revision'],
                'saved_at' => now(),
            ])->save();

            $answer->selectedOptions()->sync($optionIds);

            if ($itemPosition > $locked->furthest_position) {
                $locked->forceFill(['furthest_position' => $itemPosition])->save();
            }

            return $answer;
        });
    }

    /**
     * Token pilihan diubah ke ID internal; token dari soal lain ditolak.
     *
     * @param  array<int, string>  $tokens
     * @return array<int, int>
     */
    private function optionIds(AttemptQuestion $question, array $tokens): array
    {
        if (! $question->type->hasOptions()) {
            return [];
        }

        $tokens = array_values(array_unique($tokens));
        $ids = $question->options->whereIn('token', $tokens)->pluck('id')->all();

        if (count($ids) !== count($tokens)) {
            throw ValidationException::withMessages(['selected' => __('Pilihan jawaban tidak valid.')]);
        }

        if ($question->type !== QuestionType::MultipleSelect && count($ids) > 1) {
            throw ValidationException::withMessages(['selected' => __('Soal ini hanya boleh memiliki satu jawaban.')]);
        }

        return $ids;
    }

    private function text(?string $text): ?string
    {
        return $text === null || trim($text) === '' ? null : $text;
    }
}

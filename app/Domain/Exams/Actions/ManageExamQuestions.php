<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamQuestion;
use App\Domain\QuestionBank\Actions\SaveQuestion;
use App\Domain\QuestionBank\Enums\QuestionStatus;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\Shared\Support\Reorder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menambah, menghapus, mengurutkan, dan mengatur bobot soal di ujian.
 * Semua operasi ditolak setelah ujian terkunci (rules Bagian 8).
 */
class ManageExamQuestions
{
    public function __construct(private readonly SaveQuestion $saveQuestion) {}

    /**
     * @param  Collection<int, Question>  $questions
     */
    public function add(Exam $exam, Collection $questions): int
    {
        return $this->whileUnlocked($exam, function (Exam $exam) use ($questions) {
            $existing = $exam->examQuestions()->pluck('question_id')->all();
            $position = (int) $exam->examQuestions()->max('position');
            $added = 0;

            foreach ($questions as $question) {
                if (in_array($question->id, $existing, true)
                    || $question->status !== QuestionStatus::Active
                    || $question->superseded_at !== null
                    || $question->parent_id !== null) {
                    continue;
                }

                $exam->examQuestions()->create([
                    'question_id' => $question->id,
                    'points' => $this->saveQuestion->examPoints($question),
                    'position' => ++$position,
                ]);
                $added++;
            }

            return $added;
        });
    }

    public function updatePoints(Exam $exam, ExamQuestion $examQuestion, string $points): void
    {
        $this->whileUnlocked($exam, function () use ($examQuestion, $points): void {
            if ($examQuestion->question->type->isContainer()) {
                throw ValidationException::withMessages(['points' => __('Bobot studi kasus mengikuti jumlah bobot sub-soalnya.')]);
            }

            $examQuestion->update(['points' => $points]);
        });
    }

    public function remove(Exam $exam, ExamQuestion $examQuestion): void
    {
        $this->whileUnlocked($exam, function (Exam $exam) use ($examQuestion): void {
            $examQuestion->delete();

            foreach ($exam->examQuestions()->orderBy('position')->get() as $index => $item) {
                $item->update(['position' => $index + 1]);
            }
        });
    }

    public function move(Exam $exam, ExamQuestion $examQuestion, string $direction): void
    {
        $this->whileUnlocked($exam, fn () => Reorder::move(
            ExamQuestion::query()->where('exam_id', $exam->id),
            $examQuestion,
            $direction,
        ));
    }

    /**
     * Mengunci baris ujian agar tidak berbarengan dengan attempt pertama yang mengunci ujian.
     *
     * @template TResult
     *
     * @param  callable(Exam): TResult  $callback
     * @return TResult
     */
    private function whileUnlocked(Exam $exam, callable $callback): mixed
    {
        return DB::transaction(function () use ($exam, $callback) {
            /** @var Exam $locked */
            $locked = Exam::query()->lockForUpdate()->findOrFail($exam->id);

            if ($locked->isLocked()) {
                throw ValidationException::withMessages(['exam' => __('Soal dan bobot ujian yang sudah dikerjakan tidak dapat diubah. Duplikasi ujian untuk membuat versi baru.')]);
            }

            return $callback($locked);
        });
    }
}

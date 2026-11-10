<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamQuestion;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Membuat versi baru ujian sebagai draf: konfigurasi, soal, bobot, dan prasyarat disalin;
 * penugasan dan attempt tidak ikut. Soal yang punya versi lebih baru diganti ke versi terbaru.
 */
class DuplicateExam
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function __invoke(Exam $source, User $actor): Exam
    {
        return DB::transaction(function () use ($source, $actor) {
            $copy = $source->replicate(['public_id', 'locked_at', 'results_released_at', 'status', 'duplicated_from_id', 'created_by']);
            $copy->fill([
                'title' => __(':title (salinan)', ['title' => $source->title]),
                'status' => ExamStatus::Draft,
                'duplicated_from_id' => $source->id,
                'created_by' => $actor->id,
            ])->save();

            foreach ($source->examQuestions()->with('question')->orderBy('position')->get() as $examQuestion) {
                /** @var ExamQuestion $examQuestion */
                $copy->examQuestions()->create([
                    'question_id' => $this->latestVersionId($examQuestion->question_id),
                    'points' => $examQuestion->points,
                    'position' => $examQuestion->position,
                ]);
            }

            $copy->prerequisitePrograms()->sync($source->prerequisitePrograms()->pluck('learning_programs.id'));
            $copy->programs()->sync($source->programs()->pluck('learning_programs.id'));

            $this->logger->log('exam.duplicated', $copy, ['source' => $source->public_id], actor: $actor);

            return $copy;
        });
    }

    private function latestVersionId(int $questionId): int
    {
        $current = $questionId;

        while (($next = DB::table('questions')->where('previous_version_id', $current)->value('id')) !== null) {
            $current = (int) $next;
        }

        return $current;
    }
}

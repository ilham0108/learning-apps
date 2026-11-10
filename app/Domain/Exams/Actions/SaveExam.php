<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Models\Exam;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menyimpan konfigurasi ujian. Ujian terkunci (sudah ada attempt) masih boleh diubah
 * judul, deskripsi, jadwal, dan kebijakan publikasinya, tetapi tidak boleh kembali ke draf.
 * Aturan penilaian (passing grade, kebijakan percobaan, navigasi) di-snapshot ke setiap
 * attempt, sehingga perubahannya tidak memengaruhi attempt yang sudah ada.
 */
class SaveExam
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $prerequisiteProgramIds
     * @param  array<int, int>  $relatedProgramIds
     */
    public function __invoke(?Exam $exam, array $data, array $prerequisiteProgramIds, array $relatedProgramIds, User $actor): Exam
    {
        return DB::transaction(function () use ($exam, $data, $prerequisiteProgramIds, $relatedProgramIds, $actor) {
            $isNew = $exam === null;
            $exam ??= new Exam(['created_by' => $actor->id]);
            $previousStatus = $exam->status;

            if ($exam->isLocked() && $data['status'] === ExamStatus::Draft) {
                throw ValidationException::withMessages(['status' => __('Ujian yang sudah dikerjakan tidak dapat dikembalikan ke draf.')]);
            }

            if ($data['status'] === ExamStatus::Published && ($isNew || ! $exam->examQuestions()->exists())) {
                throw ValidationException::withMessages(['status' => __('Tambahkan minimal satu soal sebelum menerbitkan ujian.')]);
            }

            $exam->fill($data)->save();
            $exam->prerequisitePrograms()->sync($prerequisiteProgramIds);
            $exam->programs()->sync($relatedProgramIds);

            if ($isNew) {
                $this->logger->log('exam.created', $exam, actor: $actor);
            } elseif ($previousStatus !== $exam->status) {
                $this->logger->log('exam.status_changed', $exam, [
                    'from' => $previousStatus->value,
                    'to' => $exam->status->value,
                ], actor: $actor);
            }

            return $exam;
        });
    }
}

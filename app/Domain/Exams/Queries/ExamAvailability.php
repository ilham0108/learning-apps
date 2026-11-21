<?php

namespace App\Domain\Exams\Queries;

use App\Domain\Candidates\Support\ProfileCompleteness;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Queries\ProgramProgress;
use Carbon\CarbonInterface;

/**
 * Menentukan apakah kandidat dapat memulai ujian dari sebuah penugasan, menurut
 * waktu server. Dipakai oleh daftar ujian, halaman instruksi, dan StartAttempt,
 * sehingga aturan yang ditampilkan sama dengan aturan yang ditegakkan.
 */
class ExamAvailability
{
    public const IN_PROGRESS = 'in_progress';

    public const AVAILABLE = 'available';

    public const UNAVAILABLE = 'unavailable';

    public const NOT_OPEN = 'not_open';

    public const CLOSED = 'closed';

    public const NO_ATTEMPTS_LEFT = 'no_attempts_left';

    public const PROFILE_INCOMPLETE = 'profile_incomplete';

    public const PREREQUISITES_INCOMPLETE = 'prerequisites_incomplete';

    public function __construct(private readonly ProgramProgress $progress) {}

    /**
     * @return array{state: string, attempts_used: int, attempts_left: int, active_attempt: ExamAttempt|null, incomplete_programs: array<int, LearningProgram>}
     */
    public function check(ExamAssignment $assignment, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $exam = $assignment->exam;
        $attempts = $assignment->attempts()->get();
        $active = $attempts->first(fn (ExamAttempt $attempt) => $attempt->isInProgress());
        $used = $attempts->count();
        $left = max(0, $assignment->effectiveMaxAttempts() - $used);
        $incomplete = $this->incompletePrograms($assignment);

        $state = match (true) {
            $active !== null => self::IN_PROGRESS,
            $exam->status !== ExamStatus::Published => self::UNAVAILABLE,
            $assignment->effectiveOpensAt() !== null && $now->lessThan($assignment->effectiveOpensAt()) => self::NOT_OPEN,
            $assignment->effectiveClosesAt() !== null && $now->greaterThanOrEqualTo($assignment->effectiveClosesAt()) => self::CLOSED,
            $left === 0 => self::NO_ATTEMPTS_LEFT,
            ! ProfileCompleteness::isComplete($assignment->application->candidateProfile) => self::PROFILE_INCOMPLETE,
            $incomplete !== [] => self::PREREQUISITES_INCOMPLETE,
            default => self::AVAILABLE,
        };

        return [
            'state' => $state,
            'attempts_used' => $used,
            'attempts_left' => $left,
            'active_attempt' => $active,
            'incomplete_programs' => $incomplete,
        ];
    }

    /**
     * Program prasyarat yang materi wajibnya belum selesai semua (aturan B6).
     *
     * @return array<int, LearningProgram>
     */
    private function incompletePrograms(ExamAssignment $assignment): array
    {
        $programs = $assignment->exam->prerequisitePrograms()->get();

        if ($programs->isEmpty()) {
            return [];
        }

        $profileId = $assignment->application->candidate_profile_id;
        $summaries = $this->progress->forCandidate($profileId, $programs->map(fn (LearningProgram $program) => $program->id)->all());

        return $programs
            ->filter(fn (LearningProgram $program) => $summaries[$program->id]->requiredCompleted < $summaries[$program->id]->requiredTotal)
            ->values()
            ->all();
    }

    /**
     * Pesan penolakan untuk state yang tidak mengizinkan mulai ujian.
     */
    public static function message(string $state): string
    {
        return match ($state) {
            self::UNAVAILABLE => __('Ujian ini sedang tidak tersedia.'),
            self::NOT_OPEN => __('Ujian belum dibuka.'),
            self::CLOSED => __('Ujian sudah ditutup.'),
            self::NO_ATTEMPTS_LEFT => __('Kesempatan mengerjakan ujian ini sudah habis.'),
            self::PROFILE_INCOMPLETE => __('Lengkapi data diri Anda sebelum memulai ujian.'),
            self::PREREQUISITES_INCOMPLETE => __('Selesaikan dulu materi wajib dari program prasyarat.'),
            default => __('Ujian tidak dapat dimulai.'),
        };
    }
}

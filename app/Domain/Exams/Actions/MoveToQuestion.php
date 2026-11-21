<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Exams\Enums\NavigationMode;
use App\Domain\Exams\Exceptions\AttemptRejected;
use App\Domain\Exams\Models\ExamAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mencatat soal yang sedang dibuka agar posisi pulih setelah refresh. Pada
 * navigasi maju saja, posisi terjauh tidak bisa mundur dan soal sebelumnya
 * terkunci (aturan B14).
 */
class MoveToQuestion
{
    public function __invoke(ExamAttempt $attempt, int $position): ExamAttempt
    {
        return DB::transaction(function () use ($attempt, $position) {
            /** @var ExamAttempt $locked */
            $locked = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if (! $locked->isInProgress() || $locked->isPastDeadline()) {
                throw AttemptRejected::finished();
            }

            $count = $locked->questions()->whereNull('parent_id')->count();

            if ($position < 1 || $position > $count) {
                throw ValidationException::withMessages(['position' => __('Nomor soal tidak valid.')]);
            }

            if ($locked->navigation === NavigationMode::ForwardOnly && $position < $locked->furthest_position) {
                throw AttemptRejected::questionLocked();
            }

            $locked->forceFill([
                'current_position' => $position,
                'furthest_position' => max($locked->furthest_position, $position),
            ])->save();

            return $locked;
        });
    }
}

<?php

namespace App\Domain\Candidates\Actions;

use App\Domain\Candidates\Enums\EvaluationCriterion;
use App\Domain\Candidates\Enums\EvaluationStatus;
use App\Domain\Candidates\Enums\Recommendation;
use App\Domain\Candidates\Models\CandidateEvaluation;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menyimpan evaluasi milik penilai untuk satu lamaran. Evaluasi final tidak dapat diubah.
 * Keputusan rekrutmen tetap diambil HR lewat perubahan tahap, bukan otomatis dari evaluasi.
 */
class SaveEvaluation
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, array{score: int|null, note: string|null}>  $scores  Kunci: nilai EvaluationCriterion.
     */
    public function __invoke(
        RecruitmentApplication $application,
        User $evaluator,
        array $scores,
        ?Recommendation $recommendation,
        ?string $summary,
        bool $finalize,
    ): CandidateEvaluation {
        return DB::transaction(function () use ($application, $evaluator, $scores, $recommendation, $summary, $finalize) {
            $evaluation = CandidateEvaluation::query()
                ->where('recruitment_application_id', $application->id)
                ->where('evaluator_id', $evaluator->id)
                ->lockForUpdate()
                ->first();

            if ($evaluation?->status === EvaluationStatus::Final) {
                throw ValidationException::withMessages(['evaluation' => __('Evaluasi yang sudah final tidak dapat diubah.')]);
            }

            if ($finalize && $recommendation === null) {
                throw ValidationException::withMessages(['recommendation' => __('Rekomendasi wajib diisi sebelum evaluasi difinalkan.')]);
            }

            $evaluation ??= new CandidateEvaluation([
                'recruitment_application_id' => $application->id,
                'evaluator_id' => $evaluator->id,
            ]);

            $evaluation->fill([
                'recommendation' => $recommendation,
                'summary' => $summary,
                'status' => $finalize ? EvaluationStatus::Final : EvaluationStatus::Draft,
                'finalized_at' => $finalize ? now() : null,
            ])->save();

            foreach (EvaluationCriterion::cases() as $criterion) {
                $evaluation->scores()->updateOrCreate(
                    ['criterion' => $criterion],
                    [
                        'score' => $scores[$criterion->value]['score'] ?? null,
                        'note' => $scores[$criterion->value]['note'] ?? null,
                    ],
                );
            }

            if ($finalize) {
                $this->logger->log('evaluation.finalized', $evaluation, [
                    'recommendation' => $recommendation->value,
                ], actor: $evaluator);
            }

            return $evaluation;
        });
    }
}

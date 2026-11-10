<?php

namespace App\Http\Resources\Candidates;

use App\Domain\Candidates\Enums\EvaluationCriterion;
use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\ApplicationStageHistory;
use App\Domain\Candidates\Models\CandidateEvaluation;
use App\Domain\Candidates\Models\CandidateEvaluationScore;
use App\Domain\Candidates\Models\InternalNote;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lamaran lengkap untuk HR: tahap, riwayat, catatan internal, dan evaluasi.
 * Tidak pernah dipakai di halaman kandidat.
 *
 * @mixin RecruitmentApplication
 */
class ApplicationDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $viewer */
        $viewer = $request->user();

        return [
            'id' => $this->public_id,
            'campaign' => [
                'id' => $this->campaign->public_id,
                'name' => $this->campaign->name,
                'status' => $this->campaign->status->value,
                'position' => $this->campaign->position->name,
                'department' => $this->campaign->position->department->name,
            ],
            'stage' => $this->stage->value,
            'stage_changed_at' => $this->stage_changed_at->toIso8601String(),
            'applied_at' => $this->applied_at->toIso8601String(),
            'allowed_stages' => $this->allowedStagesFor($viewer),
            'history' => $this->stageHistories->sortByDesc('id')->map(fn (ApplicationStageHistory $history) => [
                'from' => $history->from_stage?->value,
                'to' => $history->to_stage->value,
                'reason' => $history->reason,
                'changed_by' => $history->changer?->name,
                'created_at' => $history->created_at?->toIso8601String(),
            ])->values()->all(),
            'notes' => $this->internalNotes->sortByDesc('id')->map(fn (InternalNote $note) => [
                'id' => $note->public_id,
                'body' => $note->body,
                'author' => $note->author?->name,
                'created_at' => $note->created_at?->toIso8601String(),
                'updated_at' => $note->updated_at?->toIso8601String(),
                'can_edit' => $viewer->can('update', $note),
            ])->values()->all(),
            'evaluations' => $this->evaluations->map(fn (CandidateEvaluation $evaluation) => [
                'id' => $evaluation->public_id,
                'evaluator' => $evaluation->evaluator?->name,
                'is_mine' => $evaluation->evaluator_id === $viewer->id,
                'status' => $evaluation->status->value,
                'recommendation' => $evaluation->recommendation?->value,
                'summary' => $evaluation->summary,
                'finalized_at' => $evaluation->finalized_at?->toIso8601String(),
                'scores' => collect(EvaluationCriterion::cases())->map(function (EvaluationCriterion $criterion) use ($evaluation) {
                    /** @var CandidateEvaluationScore|null $score */
                    $score = $evaluation->scores->firstWhere('criterion', $criterion);

                    return [
                        'criterion' => $criterion->value,
                        'score' => $score?->score,
                        'note' => $score?->note,
                    ];
                })->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Tahap tujuan yang boleh dipilih pengguna ini, agar UI hanya menawarkan pilihan yang valid.
     * Server tetap memvalidasi ulang saat perubahan dikirim.
     *
     * @return list<string>
     */
    private function allowedStagesFor(User $viewer): array
    {
        $targets = array_filter(
            RecruitmentStage::cases(),
            fn (RecruitmentStage $target) => $this->stage->canBeChangedManuallyTo($target, $viewer->isSuperAdmin()),
        );

        return array_values(array_map(fn (RecruitmentStage $stage) => $stage->value, $targets));
    }
}

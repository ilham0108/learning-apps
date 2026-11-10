<?php

namespace App\Domain\Candidates\Models;

use App\Domain\Candidates\Enums\EvaluationCriterion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $candidate_evaluation_id
 * @property EvaluationCriterion $criterion
 * @property int|null $score
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['candidate_evaluation_id', 'criterion', 'score', 'note'])]
class CandidateEvaluationScore extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'criterion' => EvaluationCriterion::class,
            'score' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CandidateEvaluation, $this>
     */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(CandidateEvaluation::class, 'candidate_evaluation_id');
    }
}

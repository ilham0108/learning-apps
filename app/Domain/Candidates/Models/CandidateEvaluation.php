<?php

namespace App\Domain\Candidates\Models;

use App\Domain\Candidates\Enums\EvaluationStatus;
use App\Domain\Candidates\Enums\Recommendation;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CandidateEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $recruitment_application_id
 * @property int|null $evaluator_id
 * @property Recommendation|null $recommendation
 * @property string|null $summary
 * @property EvaluationStatus $status
 * @property CarbonImmutable|null $finalized_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(CandidateEvaluationFactory::class)]
#[Fillable(['recruitment_application_id', 'evaluator_id', 'recommendation', 'summary', 'status', 'finalized_at'])]
class CandidateEvaluation extends Model
{
    /** @use HasFactory<CandidateEvaluationFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recommendation' => Recommendation::class,
            'status' => EvaluationStatus::class,
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RecruitmentApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    /**
     * @return HasMany<CandidateEvaluationScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(CandidateEvaluationScore::class);
    }
}

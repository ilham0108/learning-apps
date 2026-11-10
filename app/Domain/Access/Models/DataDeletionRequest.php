<?php

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\DataDeletionStatus;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\DataDeletionRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $candidate_profile_id
 * @property string|null $reason
 * @property DataDeletionStatus $status
 * @property string|null $decision_note
 * @property int|null $processed_by
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(DataDeletionRequestFactory::class)]
#[Fillable(['candidate_profile_id', 'reason', 'status', 'decision_note', 'processed_by', 'processed_at'])]
class DataDeletionRequest extends Model
{
    /** @use HasFactory<DataDeletionRequestFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DataDeletionStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CandidateProfile, $this>
     */
    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}

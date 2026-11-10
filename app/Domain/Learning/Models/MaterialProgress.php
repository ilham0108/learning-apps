<?php

namespace App\Domain\Learning\Models;

use App\Domain\Candidates\Models\CandidateProfile;
use Carbon\CarbonImmutable;
use Database\Factories\MaterialProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Progres kandidat per materi. Disimpan per kandidat, bukan per lamaran,
 * agar materi yang sama tidak perlu dipelajari ulang di gelombang lain.
 *
 * @property int $id
 * @property int $candidate_profile_id
 * @property int $learning_material_id
 * @property CarbonImmutable|null $first_opened_at
 * @property CarbonImmutable|null $completed_at
 * @property int $watched_seconds
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Table('material_progress')]
#[UseFactory(MaterialProgressFactory::class)]
#[Fillable(['candidate_profile_id', 'learning_material_id', 'first_opened_at', 'completed_at', 'watched_seconds'])]
class MaterialProgress extends Model
{
    /** @use HasFactory<MaterialProgressFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_opened_at' => 'datetime',
            'completed_at' => 'datetime',
            'watched_seconds' => 'integer',
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
     * @return BelongsTo<LearningMaterial, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(LearningMaterial::class, 'learning_material_id');
    }
}

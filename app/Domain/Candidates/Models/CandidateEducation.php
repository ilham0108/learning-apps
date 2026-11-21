<?php

namespace App\Domain\Candidates\Models;

use App\Domain\Candidates\Enums\EducationLevel;
use App\Domain\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $candidate_profile_id
 * @property EducationLevel $level
 * @property string $institution
 * @property string|null $major
 * @property int|null $start_year
 * @property int|null $graduation_year
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['candidate_profile_id', 'level', 'institution', 'major', 'start_year', 'graduation_year'])]
class CandidateEducation extends Model
{
    use HasPublicId;

    protected $table = 'candidate_educations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => EducationLevel::class,
            'start_year' => 'integer',
            'graduation_year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CandidateProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_profile_id');
    }
}

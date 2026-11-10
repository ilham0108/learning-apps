<?php

namespace App\Domain\Candidates\Models;

use App\Domain\Access\Models\DataDeletionRequest;
use App\Domain\Learning\Models\MaterialProgress;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Shared\Enums\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CandidateProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $candidate_number
 * @property string|null $phone
 * @property string|null $last_education
 * @property CarbonImmutable $registered_on
 * @property CarbonImmutable|null $activated_at
 * @property CarbonImmutable|null $consent_given_at
 * @property string|null $consent_policy_version
 * @property CarbonImmutable|null $anonymized_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(CandidateProfileFactory::class)]
#[Fillable(['user_id', 'candidate_number', 'phone', 'last_education', 'registered_on'])]
class CandidateProfile extends Model
{
    /** @use HasFactory<CandidateProfileFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registered_on' => 'date',
            'activated_at' => 'datetime',
            'consent_given_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    /**
     * Profil yang boleh diakses: Super Admin semua, HR jika kandidat punya lamaran di
     * gelombang yang ditugaskan, kandidat hanya profilnya sendiri.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        match ($user->role) {
            Role::SuperAdmin => null,
            Role::Hr => $query->whereIn(
                $this->qualifyColumn('id'),
                RecruitmentApplication::query()->select('candidate_profile_id')->visibleTo($user),
            ),
            Role::Candidate => $query->whereBelongsTo($user),
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<RecruitmentApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(RecruitmentApplication::class);
    }

    /**
     * @return HasMany<MaterialProgress, $this>
     */
    public function materialProgress(): HasMany
    {
        return $this->hasMany(MaterialProgress::class);
    }

    /**
     * @return HasMany<DataDeletionRequest, $this>
     */
    public function deletionRequests(): HasMany
    {
        return $this->hasMany(DataDeletionRequest::class);
    }
}

<?php

namespace App\Domain\Candidates\Models;

use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Learning\Models\ProgramAssignment;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Shared\Enums\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\RecruitmentApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lamaran kandidat pada satu gelombang. Posisi dan departemen mengikuti gelombang.
 *
 * @property int $id
 * @property string $public_id
 * @property int $candidate_profile_id
 * @property int $recruitment_campaign_id
 * @property RecruitmentStage $stage
 * @property CarbonImmutable $stage_changed_at
 * @property CarbonImmutable $applied_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(RecruitmentApplicationFactory::class)]
#[Fillable(['candidate_profile_id', 'recruitment_campaign_id', 'stage', 'stage_changed_at', 'applied_at'])]
class RecruitmentApplication extends Model
{
    /** @use HasFactory<RecruitmentApplicationFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => RecruitmentStage::class,
            'stage_changed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * Lamaran yang boleh diakses pengguna: Super Admin semua, HR dari gelombang yang
     * ditugaskan, kandidat hanya miliknya sendiri.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        match ($user->role) {
            Role::SuperAdmin => null,
            Role::Hr => $query->whereIn($this->qualifyColumn('recruitment_campaign_id'), RecruitmentCampaign::assignedCampaignIds($user)),
            Role::Candidate => $query->whereIn(
                $this->qualifyColumn('candidate_profile_id'),
                CandidateProfile::query()->select('id')->whereBelongsTo($user),
            ),
        };
    }

    /**
     * @return BelongsTo<CandidateProfile, $this>
     */
    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    /**
     * @return BelongsTo<RecruitmentCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCampaign::class, 'recruitment_campaign_id');
    }

    /**
     * @return HasMany<ApplicationStageHistory, $this>
     */
    public function stageHistories(): HasMany
    {
        return $this->hasMany(ApplicationStageHistory::class);
    }

    /**
     * @return HasMany<InternalNote, $this>
     */
    public function internalNotes(): HasMany
    {
        return $this->hasMany(InternalNote::class);
    }

    /**
     * @return HasMany<CandidateEvaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(CandidateEvaluation::class);
    }

    /**
     * @return HasMany<ProgramAssignment, $this>
     */
    public function programAssignments(): HasMany
    {
        return $this->hasMany(ProgramAssignment::class);
    }

    /**
     * @return HasMany<ExamAssignment, $this>
     */
    public function examAssignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }
}

<?php

namespace App\Domain\Candidates\Models;

use App\Domain\Candidates\Enums\CampaignStatus;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Shared\Enums\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\RecruitmentCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property int $position_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property CampaignStatus $status
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $anonymized_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(RecruitmentCampaignFactory::class)]
#[Fillable(['name', 'position_id', 'starts_on', 'ends_on', 'status', 'closed_at', 'created_by'])]
class RecruitmentCampaign extends Model
{
    /** @use HasFactory<RecruitmentCampaignFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => CampaignStatus::class,
            'closed_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    /**
     * Gelombang yang boleh diakses pengguna: Super Admin semua, HR hanya yang ditugaskan,
     * kandidat tidak ada. Scope ini juga dipakai Policy agar hanya ada satu sumber aturan.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        match ($user->role) {
            Role::SuperAdmin => null,
            Role::Hr => $query->whereIn($this->qualifyColumn('id'), self::assignedCampaignIds($user)),
            Role::Candidate => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Subquery ID gelombang yang ditugaskan kepada seorang HR.
     */
    public static function assignedCampaignIds(User $user): QueryBuilder
    {
        return DB::table('campaign_user')
            ->select('recruitment_campaign_id')
            ->where('user_id', $user->getKey());
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * HR yang ditugaskan ke gelombang ini.
     *
     * @return BelongsToMany<User, $this>
     */
    public function hrUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'campaign_user')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /**
     * @return HasMany<RecruitmentApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(RecruitmentApplication::class);
    }
}

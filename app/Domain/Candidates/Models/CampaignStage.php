<?php

namespace App\Domain\Candidates\Models;

use App\Domain\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tahap proses dalam satu rekrutmen (misalnya Psikotes, Wawancara HR), berurutan
 * menurut `position`. Disalin dari alur default saat rekrutmen dibuat.
 *
 * @property int $id
 * @property string $public_id
 * @property int $recruitment_campaign_id
 * @property string $name
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['recruitment_campaign_id', 'name', 'position'])]
class CampaignStage extends Model
{
    use HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /**
     * @return BelongsTo<RecruitmentCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCampaign::class, 'recruitment_campaign_id');
    }

    /**
     * @return HasMany<RecruitmentApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(RecruitmentApplication::class);
    }
}

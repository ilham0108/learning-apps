<?php

namespace App\Domain\Candidates\Models;

use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $recruitment_application_id
 * @property RecruitmentStage|null $from_stage
 * @property RecruitmentStage $to_stage
 * @property string|null $reason
 * @property int|null $changed_by
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['recruitment_application_id', 'from_stage', 'to_stage', 'reason', 'changed_by'])]
class ApplicationStageHistory extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_stage' => RecruitmentStage::class,
            'to_stage' => RecruitmentStage::class,
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
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

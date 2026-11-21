<?php

namespace App\Domain\Candidates\Models;

use App\Domain\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Tahap proses di alur default. Perubahan hanya berlaku untuk rekrutmen yang
 * dibuat sesudahnya; rekrutmen yang sudah ada memakai salinannya sendiri.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'position'])]
class PipelineStageTemplate extends Model
{
    use HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}

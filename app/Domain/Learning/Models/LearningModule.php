<?php

namespace App\Domain\Learning\Models;

use App\Domain\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\LearningModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $learning_program_id
 * @property string $title
 * @property string|null $description
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(LearningModuleFactory::class)]
#[Fillable(['learning_program_id', 'title', 'description', 'position'])]
class LearningModule extends Model
{
    /** @use HasFactory<LearningModuleFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<LearningProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(LearningProgram::class, 'learning_program_id');
    }

    /**
     * @return HasMany<LearningMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(LearningMaterial::class)->orderBy('position');
    }
}

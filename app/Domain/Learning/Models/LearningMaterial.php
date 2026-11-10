<?php

namespace App\Domain\Learning\Models;

use App\Domain\Learning\Enums\MaterialType;
use App\Domain\Learning\Enums\VideoProvider;
use App\Domain\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\LearningMaterialFactory;
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
 * @property int $learning_module_id
 * @property string $title
 * @property MaterialType $type
 * @property bool $is_required
 * @property int $position
 * @property string|null $body_html
 * @property string|null $file_path
 * @property string|null $file_original_name
 * @property string|null $file_mime
 * @property int|null $file_size
 * @property string|null $external_url
 * @property VideoProvider|null $video_provider
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(LearningMaterialFactory::class)]
#[Fillable([
    'learning_module_id', 'title', 'type', 'is_required', 'position', 'body_html',
    'file_path', 'file_original_name', 'file_mime', 'file_size', 'external_url', 'video_provider',
])]
class LearningMaterial extends Model
{
    /** @use HasFactory<LearningMaterialFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MaterialType::class,
            'video_provider' => VideoProvider::class,
            'is_required' => 'boolean',
            'position' => 'integer',
            'file_size' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Materi yang masih aktif (tidak diarsipkan).
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @return BelongsTo<LearningModule, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(LearningModule::class, 'learning_module_id');
    }

    /**
     * @return HasMany<MaterialProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(MaterialProgress::class);
    }
}

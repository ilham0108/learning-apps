<?php

namespace App\Domain\QuestionBank\Models;

use App\Domain\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\QuestionCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(QuestionCategoryFactory::class)]
#[Fillable(['name', 'description'])]
class QuestionCategory extends Model
{
    /** @use HasFactory<QuestionCategoryFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}

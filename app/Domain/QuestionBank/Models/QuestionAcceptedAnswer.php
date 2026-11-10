<?php

namespace App\Domain\QuestionBank\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $question_id
 * @property string $answer
 * @property string $normalized
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['question_id', 'answer'])]
class QuestionAcceptedAnswer extends Model
{
    /**
     * Kolom `normalized` selalu diisi bersamaan dengan `answer`.
     *
     * @return Attribute<string, string>
     */
    protected function answer(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => [
                'answer' => trim($value),
                'normalized' => self::normalize($value),
            ],
        );
    }

    /**
     * Normalisasi jawaban isian singkat: abaikan huruf besar/kecil dan spasi berlebih.
     */
    public static function normalize(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($value)));
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

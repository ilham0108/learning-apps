<?php

namespace App\Domain\Exams\Models;

use Database\Factories\AttemptQuestionOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot pilihan jawaban untuk satu attempt, dengan token acak per attempt.
 *
 * @property int $id
 * @property string $token
 * @property int $attempt_question_id
 * @property string $body_html
 * @property bool $is_correct
 * @property int $position
 */
#[WithoutTimestamps]
#[UseFactory(AttemptQuestionOptionFactory::class)]
#[Fillable(['attempt_question_id', 'body_html', 'is_correct', 'position'])]
class AttemptQuestionOption extends Model
{
    /** @use HasFactory<AttemptQuestionOptionFactory> */
    use HasFactory, HasUlids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['token'];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AttemptQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(AttemptQuestion::class, 'attempt_question_id');
    }
}

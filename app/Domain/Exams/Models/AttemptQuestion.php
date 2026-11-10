<?php

namespace App\Domain\Exams\Models;

use App\Domain\Exams\Enums\ScoringRule;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\QuestionBank\Models\QuestionCategory;
use Carbon\CarbonImmutable;
use Database\Factories\AttemptQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Snapshot soal untuk satu attempt. `token` acak per attempt dipakai di halaman ujian
 * agar identitas soal bank tidak bisa dicocokkan antar-kandidat.
 *
 * @property int $id
 * @property string $token
 * @property int $exam_attempt_id
 * @property int|null $parent_id
 * @property int|null $source_question_id
 * @property int|null $question_category_id
 * @property string $category_name
 * @property int $position
 * @property QuestionType $type
 * @property string $body_html
 * @property string|null $explanation_html
 * @property string|null $rubric
 * @property string $points
 * @property ScoringRule $scoring_rule
 * @property array<array-key, mixed>|null $accepted_answers
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(AttemptQuestionFactory::class)]
#[Fillable([
    'exam_attempt_id', 'parent_id', 'source_question_id', 'question_category_id', 'category_name', 'position',
    'type', 'body_html', 'explanation_html', 'rubric', 'points', 'scoring_rule', 'accepted_answers',
])]
class AttemptQuestion extends Model
{
    /** @use HasFactory<AttemptQuestionFactory> */
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
            'position' => 'integer',
            'type' => QuestionType::class,
            'points' => 'decimal:2',
            'scoring_rule' => ScoringRule::class,
            'accepted_answers' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /**
     * @return HasMany<AttemptQuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(AttemptQuestionOption::class)->orderBy('position');
    }

    /**
     * @return HasOne<AttemptAnswer, $this>
     */
    public function answer(): HasOne
    {
        return $this->hasOne(AttemptAnswer::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function sourceQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'source_question_id');
    }

    /**
     * @return BelongsTo<QuestionCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class, 'question_category_id');
    }
}

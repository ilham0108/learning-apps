<?php

namespace App\Domain\QuestionBank\Models;

use App\Domain\Exams\Models\ExamQuestion;
use App\Domain\QuestionBank\Enums\Difficulty;
use App\Domain\QuestionBank\Enums\QuestionStatus;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Soal bank soal. Soal yang dipakai ujian terkunci tidak diedit di tempat;
 * perubahan membuat baris versi baru (lihat docs/tahap-1.md bagian 6.3).
 *
 * @property int $id
 * @property string $public_id
 * @property int $question_category_id
 * @property int|null $parent_id
 * @property int|null $previous_version_id
 * @property int $version
 * @property string|null $topic
 * @property Difficulty $difficulty
 * @property QuestionType $type
 * @property string $body_html
 * @property string|null $explanation_html
 * @property string|null $rubric
 * @property string $default_points
 * @property int $position
 * @property QuestionStatus $status
 * @property CarbonImmutable|null $superseded_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(QuestionFactory::class)]
#[Fillable([
    'question_category_id', 'parent_id', 'previous_version_id', 'version', 'topic', 'difficulty', 'type',
    'body_html', 'explanation_html', 'rubric', 'default_points', 'position', 'status', 'created_by',
])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'difficulty' => Difficulty::class,
            'type' => QuestionType::class,
            'status' => QuestionStatus::class,
            'default_points' => 'decimal:2',
            'version' => 'integer',
            'position' => 'integer',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * Versi terbaru dari setiap soal, yang tampil di daftar bank soal.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('superseded_at');
    }

    /**
     * Soal mandiri dan soal induk studi kasus (bukan sub-soal).
     *
     * @param  Builder<self>  $query
     */
    public function scopeTopLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * @return BelongsTo<QuestionCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class, 'question_category_id');
    }

    /**
     * Soal induk studi kasus.
     *
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Sub-soal studi kasus.
     *
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    /**
     * @return HasMany<QuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('position');
    }

    /**
     * @return HasMany<QuestionAcceptedAnswer, $this>
     */
    public function acceptedAnswers(): HasMany
    {
        return $this->hasMany(QuestionAcceptedAnswer::class);
    }

    /**
     * @return HasMany<ExamQuestion, $this>
     */
    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Domain\Exams\Models;

use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Enums\ExamType;
use App\Domain\Exams\Enums\MultiSelectScoring;
use App\Domain\Exams\Enums\NavigationMode;
use App\Domain\Exams\Enums\ResultRelease;
use App\Domain\Exams\Enums\ScorePolicy;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property string $title
 * @property string|null $description
 * @property ExamType $type
 * @property int $duration_minutes
 * @property string $passing_grade
 * @property int $max_attempts
 * @property CarbonImmutable|null $opens_at
 * @property CarbonImmutable|null $closes_at
 * @property bool $shuffle_questions
 * @property bool $shuffle_options
 * @property NavigationMode $navigation
 * @property ScorePolicy $score_policy
 * @property MultiSelectScoring $multi_select_scoring
 * @property ResultRelease $result_release
 * @property CarbonImmutable|null $results_released_at
 * @property bool $show_review
 * @property ExamStatus $status
 * @property CarbonImmutable|null $locked_at
 * @property int|null $duplicated_from_id
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(ExamFactory::class)]
#[Fillable([
    'title', 'description', 'type', 'duration_minutes', 'passing_grade', 'max_attempts', 'opens_at', 'closes_at',
    'shuffle_questions', 'shuffle_options', 'navigation', 'score_policy', 'multi_select_scoring',
    'result_release', 'show_review', 'status', 'duplicated_from_id', 'created_by',
])]
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExamType::class,
            'duration_minutes' => 'integer',
            'passing_grade' => 'decimal:2',
            'max_attempts' => 'integer',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'navigation' => NavigationMode::class,
            'score_policy' => ScorePolicy::class,
            'multi_select_scoring' => MultiSelectScoring::class,
            'result_release' => ResultRelease::class,
            'results_released_at' => 'datetime',
            'show_review' => 'boolean',
            'status' => ExamStatus::class,
            'locked_at' => 'datetime',
        ];
    }

    /**
     * Ujian terkunci setelah attempt pertama: soal dan bobot tidak bisa diubah lagi.
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * @return HasMany<ExamQuestion, $this>
     */
    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Question, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot(['points', 'position'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /**
     * Program yang materi wajibnya harus selesai sebelum ujian dimulai.
     *
     * @return BelongsToMany<LearningProgram, $this>
     */
    public function prerequisitePrograms(): BelongsToMany
    {
        return $this->belongsToMany(LearningProgram::class, 'exam_prerequisites')->withTimestamps();
    }

    /**
     * @return BelongsToMany<LearningProgram, $this>
     */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(LearningProgram::class, 'program_exams')->withTimestamps();
    }

    /**
     * @return HasMany<ExamAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function duplicatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicated_from_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

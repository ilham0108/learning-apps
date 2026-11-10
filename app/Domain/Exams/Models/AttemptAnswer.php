<?php

namespace App\Domain\Exams\Models;

use App\Domain\Exams\Enums\AnswerGradingStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\AttemptAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Jawaban satu soal dalam satu attempt (satu baris per soal).
 *
 * @property int $id
 * @property int $exam_attempt_id
 * @property int $attempt_question_id
 * @property string|null $text_answer
 * @property bool $is_flagged
 * @property int $client_revision
 * @property CarbonImmutable|null $saved_at
 * @property string|null $auto_points
 * @property string|null $manual_points
 * @property string|null $awarded_points
 * @property AnswerGradingStatus|null $grading_status
 * @property int|null $graded_by
 * @property CarbonImmutable|null $graded_at
 * @property string|null $grader_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(AttemptAnswerFactory::class)]
#[Fillable([
    'exam_attempt_id', 'attempt_question_id', 'text_answer', 'is_flagged', 'client_revision', 'saved_at',
    'auto_points', 'manual_points', 'awarded_points', 'grading_status', 'graded_by', 'graded_at', 'grader_note',
])]
class AttemptAnswer extends Model
{
    /** @use HasFactory<AttemptAnswerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_flagged' => 'boolean',
            'client_revision' => 'integer',
            'saved_at' => 'datetime',
            'auto_points' => 'decimal:2',
            'manual_points' => 'decimal:2',
            'awarded_points' => 'decimal:2',
            'grading_status' => AnswerGradingStatus::class,
            'graded_at' => 'datetime',
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
     * @return BelongsTo<AttemptQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(AttemptQuestion::class, 'attempt_question_id');
    }

    /**
     * @return BelongsToMany<AttemptQuestionOption, $this>
     */
    public function selectedOptions(): BelongsToMany
    {
        return $this->belongsToMany(AttemptQuestionOption::class, 'attempt_answer_options');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}

<?php

namespace App\Domain\Exams\Models;

use App\Domain\QuestionBank\Models\QuestionCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $exam_attempt_id
 * @property int|null $question_category_id
 * @property string $category_name
 * @property string $earned_points
 * @property string $max_points
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['exam_attempt_id', 'question_category_id', 'category_name', 'earned_points', 'max_points'])]
class AttemptCategoryScore extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'earned_points' => 'decimal:2',
            'max_points' => 'decimal:2',
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
     * @return BelongsTo<QuestionCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class, 'question_category_id');
    }
}

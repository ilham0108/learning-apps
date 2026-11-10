<?php

namespace App\Domain\Exams\Models;

use App\Domain\Exams\Enums\AttemptResult;
use App\Domain\Exams\Enums\AttemptStatus;
use App\Domain\Exams\Enums\GradingStatus;
use App\Domain\Exams\Enums\NavigationMode;
use App\Domain\Exams\Enums\ResultRelease;
use App\Domain\Exams\Enums\ScorePolicy;
use App\Domain\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\ExamAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu kali pengerjaan ujian. Aturan ujian yang memengaruhi penilaian
 * (passing grade, navigasi, kebijakan) di-snapshot saat attempt dibuat.
 *
 * @property int $id
 * @property string $public_id
 * @property int $exam_assignment_id
 * @property int $attempt_number
 * @property AttemptStatus $status
 * @property GradingStatus|null $grading_status
 * @property AttemptResult|null $result
 * @property int|null $active_assignment_id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $deadline_at
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $finalized_at
 * @property string|null $session_token_hash
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int $furthest_position
 * @property string|null $earned_points
 * @property string $max_points
 * @property string|null $score_percent
 * @property string $passing_grade
 * @property NavigationMode $navigation
 * @property ScorePolicy $score_policy
 * @property ResultRelease $result_release
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(ExamAttemptFactory::class)]
#[Hidden(['session_token_hash'])]
#[Fillable([
    'exam_assignment_id', 'attempt_number', 'status', 'grading_status', 'result', 'started_at', 'deadline_at',
    'submitted_at', 'finalized_at', 'session_token_hash', 'ip_address', 'user_agent', 'furthest_position',
    'earned_points', 'max_points', 'score_percent', 'passing_grade', 'navigation', 'score_policy', 'result_release',
])]
class ExamAttempt extends Model
{
    /** @use HasFactory<ExamAttemptFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'status' => AttemptStatus::class,
            'grading_status' => GradingStatus::class,
            'result' => AttemptResult::class,
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'finalized_at' => 'datetime',
            'furthest_position' => 'integer',
            'earned_points' => 'decimal:2',
            'max_points' => 'decimal:2',
            'score_percent' => 'decimal:2',
            'passing_grade' => 'decimal:2',
            'navigation' => NavigationMode::class,
            'score_policy' => ScorePolicy::class,
            'result_release' => ResultRelease::class,
        ];
    }

    /**
     * @return BelongsTo<ExamAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ExamAssignment::class, 'exam_assignment_id');
    }

    /**
     * @return HasMany<AttemptQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(AttemptQuestion::class)->orderBy('position');
    }

    /**
     * @return HasMany<AttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    /**
     * @return HasMany<AttemptEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AttemptEvent::class)->orderBy('occurred_at');
    }

    /**
     * @return HasMany<AttemptCategoryScore, $this>
     */
    public function categoryScores(): HasMany
    {
        return $this->hasMany(AttemptCategoryScore::class);
    }
}

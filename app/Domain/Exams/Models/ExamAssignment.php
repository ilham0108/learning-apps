<?php

namespace App\Domain\Exams\Models;

use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Shared\Enums\AssignmentSource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\ExamAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $exam_id
 * @property int $recruitment_application_id
 * @property AssignmentSource $assigned_via
 * @property CarbonImmutable|null $opens_at_override
 * @property CarbonImmutable|null $closes_at_override
 * @property int|null $max_attempts_override
 * @property int|null $effective_attempt_id
 * @property int|null $assigned_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(ExamAssignmentFactory::class)]
#[Fillable([
    'exam_id', 'recruitment_application_id', 'assigned_via', 'opens_at_override', 'closes_at_override',
    'max_attempts_override', 'assigned_by',
])]
class ExamAssignment extends Model
{
    /** @use HasFactory<ExamAssignmentFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_via' => AssignmentSource::class,
            'opens_at_override' => 'datetime',
            'closes_at_override' => 'datetime',
            'max_attempts_override' => 'integer',
        ];
    }

    /**
     * Jadwal khusus individu menimpa jadwal ujian.
     */
    public function effectiveOpensAt(): ?CarbonInterface
    {
        return $this->opens_at_override ?? $this->exam->opens_at;
    }

    public function effectiveClosesAt(): ?CarbonInterface
    {
        return $this->closes_at_override ?? $this->exam->closes_at;
    }

    public function effectiveMaxAttempts(): int
    {
        return $this->max_attempts_override ?? $this->exam->max_attempts;
    }

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<RecruitmentApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }

    /**
     * @return HasMany<ExamAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class)->orderBy('attempt_number');
    }

    /**
     * Attempt yang menentukan nilai penugasan menurut kebijakan percobaan.
     *
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function effectiveAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'effective_attempt_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}

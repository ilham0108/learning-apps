<?php

namespace App\Domain\Learning\Models;

use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Shared\Enums\AssignmentSource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ProgramAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $learning_program_id
 * @property int $recruitment_application_id
 * @property AssignmentSource $assigned_via
 * @property int|null $assigned_by
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(ProgramAssignmentFactory::class)]
#[Fillable(['learning_program_id', 'recruitment_application_id', 'assigned_via', 'assigned_by', 'due_at'])]
class ProgramAssignment extends Model
{
    /** @use HasFactory<ProgramAssignmentFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_via' => AssignmentSource::class,
            'due_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LearningProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(LearningProgram::class, 'learning_program_id');
    }

    /**
     * @return BelongsTo<RecruitmentApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}

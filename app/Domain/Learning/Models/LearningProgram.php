<?php

namespace App\Domain\Learning\Models;

use App\Domain\Candidates\Models\Position;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Exams\Models\Exam;
use App\Domain\Learning\Enums\ProgramStatus;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\LearningProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property int|null $target_position_id
 * @property ProgramStatus $status
 * @property int|null $estimated_minutes
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(LearningProgramFactory::class)]
#[Fillable(['name', 'description', 'category', 'target_position_id', 'status', 'estimated_minutes', 'created_by'])]
class LearningProgram extends Model
{
    /** @use HasFactory<LearningProgramFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProgramStatus::class,
            'estimated_minutes' => 'integer',
        ];
    }

    /**
     * Program terbit yang ditugaskan ke salah satu lamaran milik kandidat.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAssignedToCandidate(Builder $query, User $candidate): void
    {
        $query->where('status', ProgramStatus::Published)
            ->whereIn('id', ProgramAssignment::query()
                ->select('learning_program_id')
                ->whereIn('recruitment_application_id', RecruitmentApplication::query()->select('id')->visibleTo($candidate)));
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function targetPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'target_position_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<LearningModule, $this>
     */
    public function modules(): HasMany
    {
        return $this->hasMany(LearningModule::class)->orderBy('position');
    }

    /**
     * @return HasManyThrough<LearningMaterial, LearningModule, $this>
     */
    public function materials(): HasManyThrough
    {
        return $this->hasManyThrough(LearningMaterial::class, LearningModule::class);
    }

    /**
     * @return HasMany<ProgramAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ProgramAssignment::class);
    }

    /**
     * Ujian yang terkait dengan program ini.
     *
     * @return BelongsToMany<Exam, $this>
     */
    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'program_exams')->withTimestamps();
    }
}

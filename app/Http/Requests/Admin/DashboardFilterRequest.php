<?php

namespace App\Http\Requests\Admin;

use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\Department;
use App\Domain\Candidates\Models\Position;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Learning\Models\LearningProgram;
use App\Http\Rules\PublicIdIn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filter dashboard HR. Filter menerima `public_id` dan diubah ke ID internal di
 * server; rekrutmen di luar lingkup HR ditolak.
 */
class DashboardFilterRequest extends FormRequest
{
    public const PERIODS = [7, 30, 90];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'integer', Rule::in(self::PERIODS)],
            'campaign' => ['nullable', new PublicIdIn(RecruitmentCampaign::query()->visibleTo($this->user()))],
            'position' => ['nullable', new PublicIdIn(Position::query())],
            'department' => ['nullable', new PublicIdIn(Department::query())],
            'program' => ['nullable', new PublicIdIn(LearningProgram::query())],
            'stage' => ['nullable', Rule::enum(RecruitmentStage::class)],
        ];
    }

    /**
     * Filter untuk dikembalikan ke halaman (public_id).
     *
     * @return array{period: int|null, campaign: string|null, position: string|null, department: string|null, program: string|null, stage: string|null}
     */
    public function filters(): array
    {
        return [
            'period' => $this->validated('period') === null ? null : (int) $this->validated('period'),
            'campaign' => $this->validated('campaign'),
            'position' => $this->validated('position'),
            'department' => $this->validated('department'),
            'program' => $this->validated('program'),
            'stage' => $this->validated('stage'),
        ];
    }

    /**
     * Filter dengan ID internal untuk query dashboard.
     *
     * @return array{period_days: int|null, campaign_id: int|null, position_id: int|null, department_id: int|null, program_id: int|null, stage: string|null}
     */
    public function resolved(): array
    {
        $filters = $this->filters();

        return [
            'period_days' => $filters['period'],
            'campaign_id' => $this->internalId(RecruitmentCampaign::class, $filters['campaign']),
            'position_id' => $this->internalId(Position::class, $filters['position']),
            'department_id' => $this->internalId(Department::class, $filters['department']),
            'program_id' => $this->internalId(LearningProgram::class, $filters['program']),
            'stage' => $filters['stage'],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function internalId(string $model, ?string $publicId): ?int
    {
        return $publicId === null ? null : $model::query()->where('public_id', $publicId)->value('id');
    }
}

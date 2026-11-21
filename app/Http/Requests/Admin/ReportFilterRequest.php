<?php

namespace App\Http\Requests\Admin;

use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\Department;
use App\Domain\Candidates\Models\Position;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Exams\Models\Exam;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Support\ReportFilters;
use App\Http\Rules\PublicIdIn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Jenis laporan dan filternya. Filter menerima `public_id`; rekrutmen di luar
 * lingkup HR ditolak.
 */
class ReportFilterRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'report' => [$this->isMethod('post') ? 'required' : 'nullable', Rule::enum(ReportType::class)],
            'period' => ['nullable', 'integer', Rule::in(ReportFilters::PERIODS)],
            'campaign' => ['nullable', new PublicIdIn(RecruitmentCampaign::query()->visibleTo($this->user()))],
            'position' => ['nullable', new PublicIdIn(Position::query())],
            'department' => ['nullable', new PublicIdIn(Department::query())],
            'program' => ['nullable', new PublicIdIn(LearningProgram::query())],
            'exam' => ['nullable', new PublicIdIn(Exam::query())],
            'stage' => ['nullable', Rule::enum(RecruitmentStage::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function reportType(): ReportType
    {
        return ReportType::tryFrom((string) $this->validated('report')) ?? ReportType::Candidates;
    }

    /**
     * Filter yang sudah divalidasi, berisi public_id.
     *
     * @return array<string, string|int>
     */
    public function filters(): array
    {
        return ReportFilters::clean($this->safe()->only(ReportFilters::KEYS));
    }
}

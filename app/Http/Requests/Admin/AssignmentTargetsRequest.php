<?php

namespace App\Http\Requests\Admin;

use App\Domain\Candidates\Enums\CampaignStatus;
use App\Domain\Candidates\Models\Position;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Shared\Enums\AssignmentSource;
use App\Http\Rules\PublicIdIn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Target penugasan selalu dibatasi lingkup pengguna: HR hanya dapat menugaskan
 * ke lamaran pada gelombang yang ditugaskan kepadanya. Dipakai untuk program dan ujian.
 */
class AssignmentTargetsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $via = $this->enum('via', AssignmentSource::class);

        return [
            'via' => ['required', Rule::enum(AssignmentSource::class)],
            'campaign' => [
                Rule::requiredIf($via === AssignmentSource::Campaign), 'nullable',
                new PublicIdIn(RecruitmentCampaign::query()->visibleTo($this->user())),
            ],
            'position' => [Rule::requiredIf($via === AssignmentSource::Position), 'nullable', new PublicIdIn(Position::query())],
            'applications' => [
                Rule::requiredIf(in_array($via, [AssignmentSource::Individual, AssignmentSource::Selection], true)),
                'nullable', 'array', 'max:500',
            ],
            'applications.*' => ['distinct', new PublicIdIn(RecruitmentApplication::query()->visibleTo($this->user()))],
            'due_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    public function source(): AssignmentSource
    {
        return $this->enum('via', AssignmentSource::class) ?? AssignmentSource::Individual;
    }

    /**
     * Lamaran target sesuai mode penugasan, di dalam lingkup pengguna.
     *
     * @return Builder<RecruitmentApplication>
     */
    public function targets(): Builder
    {
        $query = RecruitmentApplication::query()->visibleTo($this->user());

        return match ($this->source()) {
            AssignmentSource::Campaign => $query->whereIn(
                'recruitment_campaign_id',
                RecruitmentCampaign::query()->select('id')->where('public_id', $this->string('campaign')->toString()),
            ),
            AssignmentSource::Position => $query->whereIn(
                'recruitment_campaign_id',
                RecruitmentCampaign::query()
                    ->select('id')
                    ->where('status', '!=', CampaignStatus::Closed)
                    ->whereIn('position_id', Position::query()->select('id')->where('public_id', $this->string('position')->toString())),
            ),
            AssignmentSource::Individual, AssignmentSource::Selection => $query->whereIn('public_id', (array) $this->input('applications', [])),
        };
    }

    /**
     * Batas waktu disimpan sebagai akhir hari di zona waktu tampilan, dalam UTC.
     */
    public function dueAt(): ?string
    {
        if (! $this->filled('due_at')) {
            return null;
        }

        return now(config('recruitment.display_timezone'))
            ->setDateFrom($this->date('due_at'))
            ->endOfDay()
            ->utc()
            ->toDateTimeString();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'campaign' => 'gelombang',
            'position' => 'posisi',
            'applications' => 'kandidat',
            'applications.*' => 'kandidat',
            'due_at' => 'batas waktu',
        ];
    }
}

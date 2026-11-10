<?php

namespace App\Http\Requests\Admin;

use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Http\Rules\PublicIdIn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filter, pencarian, dan urutan daftar kandidat. Filter gelombang hanya
 * menerima gelombang dalam lingkup pengguna.
 */
class CandidateIndexRequest extends FormRequest
{
    public const SORTABLE = ['name', 'candidate_number', 'registered_on'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'campaign' => ['nullable', new PublicIdIn(RecruitmentCampaign::query()->visibleTo($this->user()))],
            'stage' => ['nullable', Rule::enum(RecruitmentStage::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Filter yang sudah divalidasi, untuk dikembalikan ke halaman.
     *
     * @return array{search: string|null, campaign: string|null, stage: string|null, status: string|null, sort: string, direction: 'asc'|'desc'}
     */
    public function filters(): array
    {
        return [
            'search' => $this->validated('search'),
            'campaign' => $this->validated('campaign'),
            'stage' => $this->validated('stage'),
            'status' => $this->validated('status'),
            'sort' => $this->validated('sort') ?? 'registered_on',
            'direction' => $this->validated('direction') === 'asc' ? 'asc' : 'desc',
        ];
    }
}

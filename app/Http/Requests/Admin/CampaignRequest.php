<?php

namespace App\Http\Requests\Admin;

use App\Domain\Candidates\Enums\CampaignStatus;
use App\Domain\Candidates\Models\Position;
use App\Http\Rules\PublicIdIn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', new PublicIdIn(Position::query()->where('is_active', true))],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::enum(CampaignStatus::class)],
        ];
    }

    /**
     * Data siap simpan dengan public_id posisi diubah ke ID internal.
     *
     * @return array{name: string, position_id: int, starts_on: string, ends_on: string|null, status: CampaignStatus}
     */
    public function campaignData(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'position_id' => Position::query()->where('public_id', $this->string('position')->toString())->value('id'),
            'starts_on' => $this->string('starts_on')->toString(),
            'ends_on' => $this->filled('ends_on') ? $this->string('ends_on')->toString() : null,
            'status' => $this->enum('status', CampaignStatus::class),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama gelombang',
            'position' => 'posisi',
            'starts_on' => 'tanggal mulai',
            'ends_on' => 'tanggal selesai',
        ];
    }
}

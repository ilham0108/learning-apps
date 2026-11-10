<?php

namespace App\Http\Requests\Admin;

use App\Domain\Candidates\Models\Position;
use App\Domain\Learning\Enums\ProgramStatus;
use App\Http\Rules\PublicIdIn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgramRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'target_position' => ['nullable', new PublicIdIn(Position::query())],
            'status' => ['required', Rule::enum(ProgramStatus::class)],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array{name: string, description: string|null, category: string|null, target_position_id: int|null, status: ProgramStatus, estimated_minutes: int|null}
     */
    public function programData(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'description' => $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            'category' => $this->filled('category') ? $this->string('category')->trim()->toString() : null,
            'target_position_id' => $this->filled('target_position')
                ? Position::query()->where('public_id', $this->string('target_position')->toString())->value('id')
                : null,
            'status' => $this->enum('status', ProgramStatus::class) ?? ProgramStatus::Draft,
            'estimated_minutes' => $this->filled('estimated_minutes') ? $this->integer('estimated_minutes') : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama program',
            'description' => 'deskripsi',
            'category' => 'kategori',
            'target_position' => 'target posisi',
            'estimated_minutes' => 'estimasi durasi',
        ];
    }
}

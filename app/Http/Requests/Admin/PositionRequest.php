<?php

namespace App\Http\Requests\Admin;

use App\Domain\Candidates\Models\Department;
use App\Domain\Candidates\Models\Position;
use App\Http\Rules\PublicIdIn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PositionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Position|null $position */
        $position = $this->route('position');
        $departmentId = $position->department_id ?? $this->department()?->id;

        return [
            'department' => $position === null
                ? ['required', new PublicIdIn(Department::query())]
                : ['prohibited'],
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique(Position::class)->where('department_id', $departmentId)->ignore($position?->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function department(): ?Department
    {
        return Department::query()->where('public_id', $this->string('department')->toString())->first();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nama posisi', 'department' => 'departemen'];
    }
}

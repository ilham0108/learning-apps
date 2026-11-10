<?php

namespace App\Http\Requests\Admin;

use App\Domain\Shared\Enums\Role;
use App\Http\Rules\PublicIdIn;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class CampaignTeamRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hr_users' => ['present', 'array'],
            'hr_users.*' => ['distinct', new PublicIdIn(self::assignableHr())],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function hrUserIds(): array
    {
        /** @var list<string> $publicIds */
        $publicIds = $this->input('hr_users', []);

        return self::assignableHr()
            ->whereIn('public_id', $publicIds)
            ->get()
            ->map(fn (User $user) => $user->id)
            ->values()
            ->all();
    }

    /**
     * @return Builder<User>
     */
    public static function assignableHr(): Builder
    {
        return User::query()->where('role', Role::Hr)->where('is_active', true);
    }
}

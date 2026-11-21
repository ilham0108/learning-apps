<?php

namespace App\Http\Resources\Access;

use App\Domain\Access\Enums\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Data pengguna yang sedang login, dibagikan ke semua halaman Inertia.
 *
 * @mixin User
 */
class CurrentUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'job_role' => $this->jobRole?->name,
            // Hanya untuk menyesuaikan menu dan tombol; otorisasi tetap di server.
            'permissions' => array_map(fn (Permission $permission) => $permission->value, $this->resource->permissions()),
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
        ];
    }
}

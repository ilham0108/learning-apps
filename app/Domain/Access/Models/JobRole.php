<?php

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\Permission;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Jabatan staf (Head HR, HR Recruiter, Interviewer, atau buatan pengguna)
 * beserta hak aksesnya.
 *
 * @property int $id
 * @property string $public_id
 * @property string|null $key
 * @property string $name
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'description'])]
class JobRole extends Model
{
    use HasPublicId;

    public const HEAD_HR = 'head_hr';

    public const HR_RECRUITER = 'hr_recruiter';

    public const INTERVIEWER = 'interviewer';

    public static function byKey(string $key): self
    {
        return self::query()->where('key', $key)->firstOrFail();
    }

    /**
     * Jabatan sistem tidak dapat dihapus, tetapi haknya tetap bisa diatur.
     */
    public function isSystem(): bool
    {
        return $this->key !== null;
    }

    /**
     * @return array<int, Permission>
     */
    public function permissionList(): array
    {
        $granted = DB::table('job_role_permissions')->where('job_role_id', $this->id)->pluck('permission')->all();

        // Urutan mengikuti katalog agar tampilan dan perbandingan konsisten.
        return array_values(array_filter(
            Permission::cases(),
            fn (Permission $permission) => in_array($permission->value, $granted, true),
        ));
    }

    /**
     * @param  array<int, Permission>  $permissions
     */
    public function syncPermissions(array $permissions): void
    {
        DB::table('job_role_permissions')->where('job_role_id', $this->id)->delete();
        DB::table('job_role_permissions')->insert(array_map(
            fn (Permission $permission) => ['job_role_id' => $this->id, 'permission' => $permission->value],
            array_values(array_unique($permissions, SORT_REGULAR)),
        ));
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Models\JobRole;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Membuat atau mengubah jabatan beserta haknya. Untuk mencegah eskalasi hak,
 * pengguna selain Super Admin:
 * - tidak dapat memberi hak yang tidak ia miliki sendiri, dan
 * - tidak dapat mengubah jabatannya sendiri.
 * Setiap perubahan hak dicatat di audit log.
 */
class SaveJobRole
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<int, Permission>  $permissions
     */
    public function __invoke(?JobRole $jobRole, string $name, ?string $description, array $permissions, User $actor): JobRole
    {
        if (! $actor->isSuperAdmin()) {
            if ($jobRole !== null && $jobRole->id === $actor->job_role_id) {
                throw ValidationException::withMessages(['permissions' => __('Anda tidak dapat mengubah hak jabatan Anda sendiri.')]);
            }

            $before = $jobRole?->permissionList() ?? [];
            $added = array_filter($permissions, fn (Permission $permission) => ! in_array($permission, $before, true));
            $notOwned = array_filter($added, fn (Permission $permission) => ! $actor->hasPermission($permission));

            if ($notOwned !== []) {
                throw ValidationException::withMessages(['permissions' => __('Anda tidak dapat memberikan hak yang tidak Anda miliki.')]);
            }
        }

        return DB::transaction(function () use ($jobRole, $name, $description, $permissions, $actor) {
            $isNew = $jobRole === null;
            $before = $isNew ? [] : array_map(fn (Permission $p) => $p->value, $jobRole->permissionList());

            $jobRole ??= new JobRole;
            $jobRole->fill(['name' => $name, 'description' => $description])->save();
            $jobRole->syncPermissions($permissions);

            $after = array_map(fn (Permission $p) => $p->value, $permissions);

            $this->logger->log($isNew ? 'job_role.created' : 'job_role.updated', $jobRole, [
                'name' => $name,
                'added' => array_values(array_diff($after, $before)),
                'removed' => array_values(array_diff($before, $after)),
            ], actor: $actor);

            return $jobRole;
        });
    }
}

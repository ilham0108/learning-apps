<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\Actions\SaveJobRole;
use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Models\JobRole;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Jabatan dan hak aksesnya (hak `roles.manage`). Super Admin dan Head HR
 * (secara bawaan) dapat mengatur hak setiap jabatan.
 */
class JobRoleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('admin/job-roles/index', [
            'jobRoles' => JobRole::query()->withCount('users')->orderBy('name')->get()->map(fn (JobRole $jobRole) => [
                'id' => $jobRole->public_id,
                'name' => $jobRole->name,
                'description' => $jobRole->description,
                'is_system' => $jobRole->isSystem(),
                'users_count' => $jobRole->users_count,
                'permissions' => array_map(fn (Permission $permission) => $permission->value, $jobRole->permissionList()),
                // Pengguna tidak boleh mengubah jabatannya sendiri (kecuali Super Admin).
                'editable' => $user->isSuperAdmin() || $jobRole->id !== $user->job_role_id,
            ])->all(),
            'permissions' => array_map(fn (Permission $permission) => [
                'value' => $permission->value,
                'group' => $permission->group(),
                // Hak yang tidak dimiliki pengguna tidak dapat ia berikan.
                'grantable' => $user->hasPermission($permission),
            ], Permission::cases()),
        ]);
    }

    public function store(Request $request, SaveJobRole $save): RedirectResponse
    {
        $data = $this->validated($request, null);
        $save(null, $data['name'], $data['description'] ?? null, $this->permissions($data), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Jabatan dibuat.')]);

        return back();
    }

    public function update(Request $request, JobRole $jobRole, SaveJobRole $save): RedirectResponse
    {
        $data = $this->validated($request, $jobRole);
        $save($jobRole, $data['name'], $data['description'] ?? null, $this->permissions($data), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Hak jabatan :name disimpan.', ['name' => $data['name']])]);

        return back();
    }

    public function destroy(Request $request, JobRole $jobRole, ActivityLogger $logger): RedirectResponse
    {
        if ($jobRole->isSystem() || $jobRole->users()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Jabatan sistem atau jabatan yang masih dipakai staf tidak dapat dihapus.')]);

            return back();
        }

        $logger->log('job_role.deleted', null, ['name' => $jobRole->name], actor: $request->user());
        $jobRole->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Jabatan dihapus.')]);

        return back();
    }

    /**
     * @return array{name: string, description?: string|null, permissions?: array<int, string>}
     */
    private function validated(Request $request, ?JobRole $jobRole): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('job_roles', 'name')->ignore($jobRole?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permission::values())],
        ], attributes: ['name' => 'nama jabatan', 'permissions' => 'hak akses']);
    }

    /**
     * @param  array{permissions?: array<int, string>}  $data
     * @return array<int, Permission>
     */
    private function permissions(array $data): array
    {
        return array_map(fn (string $value) => Permission::from($value), $data['permissions'] ?? []);
    }
}

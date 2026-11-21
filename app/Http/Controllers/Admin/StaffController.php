<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\Actions\CreateActivationLink;
use App\Domain\Access\Actions\SaveStaffAccount;
use App\Domain\Access\Models\JobRole;
use App\Domain\Shared\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Rules\PublicIdIn;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akun staf perusahaan dan jabatannya (hak `staff.manage`). Akun Super Admin
 * tidak dikelola dari sini.
 */
class StaffController extends Controller
{
    public function index(): Response
    {
        $staff = User::query()->where('role', Role::Hr)->with('jobRole')->orderBy('name')->get();

        return Inertia::render('admin/staff/index', [
            'staff' => $staff->map(fn (User $user) => [
                'id' => $user->public_id,
                'name' => $user->name,
                'email' => $user->email,
                'job_role' => $user->jobRole === null ? null : ['id' => $user->jobRole->public_id, 'name' => $user->jobRole->name],
                'is_active' => $user->is_active,
                'is_activated' => $user->email_verified_at !== null,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ])->all(),
            'jobRoles' => JobRole::query()->orderBy('name')->get()
                ->map(fn (JobRole $jobRole) => ['id' => $jobRole->public_id, 'name' => $jobRole->name])->all(),
        ]);
    }

    public function store(Request $request, SaveStaffAccount $save): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'job_role' => ['required', new PublicIdIn(JobRole::query())],
        ], attributes: ['name' => 'nama', 'job_role' => 'jabatan']);

        $result = $save->create($data['name'], $data['email'], JobRole::query()->where('public_id', $data['job_role'])->firstOrFail(), $request->user());

        Inertia::flash('activationLink', $result['activation_link']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Akun staf dibuat. Kirim tautan aktivasi kepada :name.', ['name' => $data['name']])]);

        return to_route('admin.staff.index');
    }

    public function update(Request $request, User $staff, SaveStaffAccount $save): RedirectResponse
    {
        abort_unless($staff->isStaff(), 404);

        $data = $request->validate([
            'job_role' => ['required', new PublicIdIn(JobRole::query())],
            'is_active' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], attributes: ['job_role' => 'jabatan', 'reason' => 'alasan']);

        $save->update(
            $staff,
            JobRole::query()->where('public_id', $data['job_role'])->firstOrFail(),
            (bool) $data['is_active'],
            $data['reason'] ?? null,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Akun staf diperbarui.')]);

        return back();
    }

    public function activationLink(Request $request, User $staff, CreateActivationLink $createLink): RedirectResponse
    {
        abort_unless($staff->isStaff(), 404);

        if (! CreateActivationLink::awaitsActivation($staff)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Akun ini sudah aktif.')]);

            return back();
        }

        Inertia::flash('activationLink', $createLink($staff));

        return back();
    }
}

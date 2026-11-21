<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\JobRole;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Domain\Shared\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Membuat akun staf atau mengubah jabatan dan status aktifnya. Akun baru
 * diberi password acak yang tidak diketahui siapa pun; staf membuat password
 * sendiri lewat tautan aktivasi.
 */
class SaveStaffAccount
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly CreateActivationLink $activationLink,
    ) {}

    /**
     * @return array{user: User, activation_link: string}
     */
    public function create(string $name, string $email, JobRole $jobRole, User $actor): array
    {
        return DB::transaction(function () use ($name, $email, $jobRole, $actor) {
            $user = new User(['name' => $name, 'email' => $email, 'password' => Str::random(64)]);
            $user->forceFill(['role' => Role::Hr, 'job_role_id' => $jobRole->id, 'is_active' => true])->save();

            $this->logger->log('staff.created', $user, ['job_role' => $jobRole->name], actor: $actor);

            return ['user' => $user, 'activation_link' => ($this->activationLink)($user)];
        });
    }

    public function update(User $staff, JobRole $jobRole, bool $active, ?string $reason, User $actor): void
    {
        if ($staff->is($actor)) {
            throw ValidationException::withMessages(['job_role' => __('Anda tidak dapat mengubah jabatan atau status akun Anda sendiri.')]);
        }

        // Jabatan dengan hak yang tidak dimiliki pemberi tidak dapat diberikan (eskalasi hak).
        if (! $actor->isSuperAdmin()) {
            $notOwned = array_filter($jobRole->permissionList(), fn ($permission) => ! $actor->hasPermission($permission));

            if ($notOwned !== []) {
                throw ValidationException::withMessages(['job_role' => __('Anda tidak dapat memberikan jabatan dengan hak yang tidak Anda miliki.')]);
            }
        }

        DB::transaction(function () use ($staff, $jobRole, $active, $reason, $actor): void {
            $previous = $staff->jobRole?->name;

            if ($staff->job_role_id !== $jobRole->id) {
                $staff->forceFill(['job_role_id' => $jobRole->id])->save();
                $this->logger->log('staff.role_changed', $staff, ['from' => $previous, 'to' => $jobRole->name], $reason, $actor);
            }

            if ($staff->is_active !== $active) {
                $staff->forceFill(['is_active' => $active])->save();
                $this->logger->log($active ? 'staff.activated' : 'staff.deactivated', $staff, [], $reason, $actor);
            }
        });
    }
}

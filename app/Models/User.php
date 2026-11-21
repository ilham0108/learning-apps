<?php

namespace App\Models;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Models\JobRole;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Shared\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Role $role
 * @property int|null $job_role_id
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['id', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicId, Notifiable, TwoFactorAuthenticatable;

    /** @var array<int, Permission>|null Hak efektif, disimpan selama satu request. */
    protected ?array $permissionCache = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<CandidateProfile, $this>
     */
    public function candidateProfile(): HasOne
    {
        return $this->hasOne(CandidateProfile::class);
    }

    /**
     * Rekrutmen yang ditugaskan kepada HR ini.
     *
     * @return BelongsToMany<RecruitmentCampaign, $this>
     */
    public function assignedCampaigns(): BelongsToMany
    {
        return $this->belongsToMany(RecruitmentCampaign::class, 'campaign_user')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SuperAdmin);
    }

    public function isHr(): bool
    {
        return $this->hasRole(Role::Hr);
    }

    public function isCandidate(): bool
    {
        return $this->hasRole(Role::Candidate);
    }

    /**
     * Staf perusahaan (selain Super Admin) yang haknya ditentukan jabatan.
     */
    public function isStaff(): bool
    {
        return $this->hasRole(Role::Hr);
    }

    /**
     * Super Admin selalu memiliki semua hak; kandidat tidak memiliki hak staf apa pun.
     */
    public function hasPermission(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return $this->permissionCache ??= match ($this->role) {
            Role::SuperAdmin => Permission::cases(),
            Role::Candidate => [],
            Role::Hr => $this->jobRole?->permissionList() ?? [],
        };
    }

    public function forgetPermissions(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('jobRole');
    }

    /**
     * Melihat semua rekrutmen, bukan hanya yang ditugaskan.
     */
    public function seesAllCampaigns(): bool
    {
        return $this->hasPermission(Permission::CampaignsViewAll);
    }

    /**
     * @return BelongsTo<JobRole, $this>
     */
    public function jobRole(): BelongsTo
    {
        return $this->belongsTo(JobRole::class);
    }
}

<?php

namespace Tests\Feature\Access;

use App\Domain\Access\Actions\CreateActivationLink;
use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Models\JobRole;
use App\Domain\Candidates\Enums\CandidateDocumentType;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Shared\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsNoInternalIds;
use Tests\TestCase;

class JobRoleTest extends TestCase
{
    use AssertsNoInternalIds, RefreshDatabase;

    public function test_existing_roles_are_created_with_default_permissions()
    {
        $this->assertEqualsCanonicalizing(
            Permission::defaults()['interviewer'],
            JobRole::byKey(JobRole::INTERVIEWER)->permissionList(),
        );
        $this->assertTrue(User::factory()->headHr()->create()->hasPermission(Permission::RolesManage));
        $this->assertFalse(User::factory()->hr()->create()->hasPermission(Permission::CampaignsViewAll));
        $this->assertTrue(User::factory()->superAdmin()->create()->hasPermission(Permission::AuditView));
        $this->assertSame([], CandidateProfile::factory()->create()->user->permissions());
    }

    public function test_interviewers_can_evaluate_but_not_manage_candidates()
    {
        $interviewer = User::factory()->interviewer()->create();
        $campaign = RecruitmentCampaign::factory()->assignedTo($interviewer)->create();
        $application = RecruitmentApplication::factory()->for($campaign, 'campaign')->create();
        $profile = $application->candidateProfile;
        $profile->update(['religion' => 'islam']);
        $ktp = $profile->documents()->create([
            'type' => CandidateDocumentType::IdCard, 'file_path' => 'x.pdf', 'file_original_name' => 'ktp.pdf',
            'file_mime' => 'application/pdf', 'file_size' => 10,
        ]);

        $this->actingAs($interviewer);

        $this->get(route('admin.candidates.show', $profile))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('personalData.sensitive_hidden', true)
            ->where('personalData.personal.religion', null)
            ->has('personalData.documents', 0)
            ->where('auth.user.job_role', 'Interviewer')
            ->where('auth.user.permissions', ['notes.write', 'evaluations.write']));
        $this->get(URL::temporarySignedRoute('candidate-documents.file', now()->addMinutes(5), $ktp))->assertNotFound();

        $this->post(route('admin.applications.notes.store', $application), ['body' => 'Komunikasi baik.'])->assertSessionHasNoErrors();
        $this->patch(route('admin.applications.stage.update', $application), ['target' => 'withdrawn', 'reason' => 'Uji hak'])->assertForbidden();
        $this->post(route('admin.candidates.store'), [])->assertForbidden();

        $this->get(route('admin.programs.index'))->assertForbidden();

        foreach (['admin.reports.index', 'admin.questions.index', 'admin.grading.index', 'admin.staff.index', 'admin.job-roles.index'] as $route) {
            $this->get(route($route))->assertNotFound();
        }
    }

    public function test_head_hr_sees_every_campaign_without_being_assigned()
    {
        RecruitmentApplication::factory()->count(2)->create();

        $this->actingAs(User::factory()->headHr()->create())
            ->get(route('admin.campaigns.index'))
            ->assertInertia(fn (Assert $page) => $page->where('campaigns.meta.total', 2));

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('admin.campaigns.index'))
            ->assertInertia(fn (Assert $page) => $page->where('campaigns.meta.total', 0));
    }

    public function test_permissions_can_be_changed_and_take_effect_immediately()
    {
        $recruiter = JobRole::byKey(JobRole::HR_RECRUITER);
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->get(route('admin.reports.index'))->assertOk();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('admin.job-roles.update', $recruiter), [
                'name' => 'HR Recruiter',
                'permissions' => ['campaigns.manage', 'candidates.manage'],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($hr->fresh())->get(route('admin.reports.index'))->assertNotFound();
        $log = ActivityLog::query()->where('action', 'job_role.updated')->sole();
        $this->assertContains('reports.view', $log->properties['removed']);
    }

    public function test_head_hr_cannot_escalate_or_change_their_own_role()
    {
        $headHr = User::factory()->headHr()->create();
        $interviewer = JobRole::byKey(JobRole::INTERVIEWER);
        $this->actingAs($headHr);

        // Boleh memberi hak yang ia miliki.
        $this->put(route('admin.job-roles.update', $interviewer), [
            'name' => 'Interviewer', 'permissions' => ['notes.write', 'evaluations.write', 'reports.view'],
        ])->assertSessionHasNoErrors();

        // Tidak boleh memberi hak yang tidak ia miliki (audit log, akun staf).
        $this->put(route('admin.job-roles.update', $interviewer), [
            'name' => 'Interviewer', 'permissions' => ['notes.write', 'audit.view'],
        ])->assertSessionHasErrors('permissions');
        $this->post(route('admin.job-roles.store'), ['name' => 'Auditor', 'permissions' => ['audit.view']])
            ->assertSessionHasErrors('permissions');

        // Tidak boleh mengubah jabatannya sendiri.
        $this->put(route('admin.job-roles.update', JobRole::byKey(JobRole::HEAD_HR)), [
            'name' => 'Head HR', 'permissions' => Permission::values(),
        ])->assertSessionHasErrors('permissions');

        $this->assertFalse(JobRole::byKey(JobRole::HEAD_HR)->users()->first()->fresh()->hasPermission(Permission::AuditView));
        $this->get(route('admin.job-roles.index'))->assertInertia(fn (Assert $page) => $page
            ->where('permissions.21.value', 'audit.view')
            ->where('permissions.21.grantable', false));
    }

    public function test_custom_roles_can_be_created_and_deleted_but_system_roles_cannot()
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post(route('admin.job-roles.store'), ['name' => 'Kepala Departemen', 'permissions' => ['evaluations.write', 'reports.view']])
            ->assertSessionHasNoErrors();
        $custom = JobRole::query()->where('name', 'Kepala Departemen')->sole();
        $this->assertNull($custom->key);

        $this->delete(route('admin.job-roles.destroy', JobRole::byKey(JobRole::INTERVIEWER)))->assertRedirect();
        $this->assertNotNull(JobRole::query()->where('key', JobRole::INTERVIEWER)->first());

        $this->delete(route('admin.job-roles.destroy', $custom))->assertRedirect();
        $this->assertNull($custom->fresh());
    }

    public function test_staff_accounts_are_created_with_an_activation_link()
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name' => 'Dewi Interviewer',
            'email' => 'dewi@perusahaan.test',
            'job_role' => JobRole::byKey(JobRole::INTERVIEWER)->public_id,
        ])->assertRedirect(route('admin.staff.index'));

        $staff = User::query()->where('email', 'dewi@perusahaan.test')->sole();
        $this->assertTrue($staff->isStaff());
        $this->assertNull($staff->email_verified_at);
        $this->assertTrue(ActivityLog::query()->where('action', 'staff.created')->exists());
        $this->assertDatabaseHas('account_activation_tokens', ['email' => 'dewi@perusahaan.test']);

        // Tautan aktivasi: staf membuat password lalu masuk ke beranda admin.
        $token = CreateActivationLink::broker()->createToken($staff);
        auth()->logout();
        $this->post(route('activation.store', ['user' => $staff, 'token' => $token]), [
            'password' => 'Password-Kuat-123!',
            'password_confirmation' => 'Password-Kuat-123!',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertNotNull($staff->fresh()->email_verified_at);
        $this->assertAuthenticatedAs($staff->fresh());
    }

    public function test_staff_roles_can_be_changed_but_not_by_the_same_person()
    {
        $admin = User::factory()->superAdmin()->create();
        $staff = User::factory()->hr()->create();

        $this->actingAs($admin)->patch(route('admin.staff.update', $staff), [
            'job_role' => JobRole::byKey(JobRole::INTERVIEWER)->public_id,
            'is_active' => true,
            'reason' => 'Pindah ke tim wawancara',
        ])->assertSessionHasNoErrors();

        $this->assertSame(JobRole::byKey(JobRole::INTERVIEWER)->id, $staff->fresh()->job_role_id);
        $this->assertSame('Pindah ke tim wawancara', ActivityLog::query()->where('action', 'staff.role_changed')->sole()->reason);

        // Head HR tidak memiliki hak akun staf secara bawaan.
        $this->actingAs(User::factory()->headHr()->create())->get(route('admin.staff.index'))->assertNotFound();

        $response = $this->actingAs($admin)->get(route('admin.staff.index'))->assertOk();
        $this->assertResponseHasNoInternalIds($response);
    }
}

<?php

namespace Tests\Feature\Database;

use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\Demo\DemoSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seeder_creates_the_super_admin_from_configuration()
    {
        config([
            'recruitment.super_admin.email' => 'root@example.test',
            'recruitment.super_admin.password' => 'secret-password',
        ]);

        $this->seed(ProductionSeeder::class);

        $admin = User::query()->where('email', 'root@example.test')->sole();
        $this->assertSame(Role::SuperAdmin, $admin->role);
        $this->assertTrue(Hash::check('secret-password', $admin->password));
        $this->assertSame('2026-10', SystemSetting::valueOf(SystemSetting::CONSENT_POLICY_VERSION));
        $this->assertSame(12, SystemSetting::valueOf(SystemSetting::RETENTION_MONTHS));
    }

    public function test_production_seeder_is_idempotent_and_never_overwrites_existing_data()
    {
        config([
            'recruitment.super_admin.email' => 'root@example.test',
            'recruitment.super_admin.password' => 'first-password',
        ]);
        $this->seed(ProductionSeeder::class);
        SystemSetting::put(SystemSetting::RETENTION_MONTHS, 24);

        config(['recruitment.super_admin.password' => 'second-password']);
        $this->seed(ProductionSeeder::class);

        $this->assertSame(1, User::query()->count());
        $this->assertTrue(Hash::check('first-password', User::query()->sole()->password));
        $this->assertSame(24, SystemSetting::valueOf(SystemSetting::RETENTION_MONTHS));
    }

    public function test_production_seeder_skips_the_super_admin_without_credentials()
    {
        config([
            'recruitment.super_admin.email' => null,
            'recruitment.super_admin.password' => null,
        ]);

        $this->seed(ProductionSeeder::class);

        $this->assertSame(0, User::query()->count());
    }

    public function test_demo_seeder_refuses_to_run_in_production()
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);

        (new DemoSeeder)->run();
    }

    public function test_demo_seeder_builds_a_consistent_data_set()
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(2, User::query()->where('role', Role::Hr)->count());
        $this->assertNotNull(User::query()->where('email', 'kandidat@demo.test')->first()?->candidateProfile);

        // Setiap lamaran memiliki riwayat yang diawali tahap awal dan diakhiri tahap saat ini.
        RecruitmentApplication::query()->with('stageHistories')->get()->each(function (RecruitmentApplication $application) {
            $histories = $application->stageHistories->sortBy('id')->values();

            $this->assertNull($histories->first()->from_stage);
            $this->assertContains($histories->first()->to_stage, [RecruitmentStage::Invited, RecruitmentStage::Registered]);
            $this->assertSame($application->stage, $histories->last()->to_stage);

            $histories->skip(1)->each(fn ($history) => $this->assertTrue(
                $history->from_stage->canTransitionTo($history->to_stage),
                "Transisi {$history->from_stage->value} → {$history->to_stage->value} tidak valid.",
            ));
        });

        // Satu kandidat memiliki lamaran di dua gelombang.
        $this->assertSame(1, RecruitmentApplication::query()
            ->selectRaw('candidate_profile_id')
            ->groupBy('candidate_profile_id')
            ->havingRaw('count(*) > 1')
            ->get()
            ->count());
    }
}

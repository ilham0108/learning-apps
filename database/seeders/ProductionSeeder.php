<?php

namespace Database\Seeders;

use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data minimum yang dibutuhkan di semua lingkungan, termasuk produksi.
 * Aman dijalankan berulang: data yang sudah ada tidak ditimpa.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSystemSettings();
        $this->seedSuperAdmin();
    }

    private function seedSystemSettings(): void
    {
        /** @var array<string, mixed> $defaults */
        $defaults = config('recruitment.defaults');

        foreach ($defaults as $key => $value) {
            SystemSetting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    private function seedSuperAdmin(): void
    {
        /** @var array{name: string, email: ?string, password: ?string} $config */
        $config = config('recruitment.super_admin');

        if (blank($config['email']) || blank($config['password'])) {
            $this->command?->warn('SUPER_ADMIN_EMAIL atau SUPER_ADMIN_PASSWORD kosong; Super Admin tidak dibuat.');

            return;
        }

        $user = User::query()->firstOrNew(['email' => $config['email']]);

        if ($user->exists) {
            return;
        }

        $user->forceFill([
            'name' => $config['name'],
            'password' => $config['password'],
            'role' => Role::SuperAdmin,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();
    }
}

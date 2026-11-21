<?php

use App\Domain\Access\Enums\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Jabatan staf dan hak aksesnya. Tiga jabatan sistem dibuat di sini (bukan
     * di seeder) karena aplikasi membutuhkannya di produksi. Akun HR yang sudah
     * ada menjadi HR Recruiter, yang haknya sama dengan role HR sebelumnya.
     */
    public function up(): void
    {
        Schema::create('job_roles', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            // Kunci tetap untuk jabatan sistem; null untuk jabatan buatan pengguna.
            $table->string('key', 50)->nullable()->unique();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('job_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_role_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 50);

            $table->unique(['job_role_id', 'permission']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('job_role_id')->nullable()->after('role')->constrained()->restrictOnDelete();
        });

        $now = now();
        $roles = [
            'head_hr' => ['Head HR', 'Mengatur alur tahap default, tim HR per rekrutmen, dan hak akses jabatan. Melihat semua rekrutmen.'],
            'hr_recruiter' => ['HR Recruiter', 'Mengelola rekrutmen yang ditugaskan: kandidat, materi, ujian, penilaian, dan tahap rekrutmen.'],
            'interviewer' => ['Interviewer', 'Melihat kandidat di rekrutmen yang ditugaskan dan mengisi evaluasi wawancara.'],
        ];

        foreach ($roles as $key => [$name, $description]) {
            $id = DB::table('job_roles')->insertGetId([
                'public_id' => strtolower((string) Str::ulid()),
                'key' => $key,
                'name' => $name,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('job_role_permissions')->insert(array_map(
                fn (Permission $permission) => ['job_role_id' => $id, 'permission' => $permission->value],
                Permission::defaults()[$key],
            ));
        }

        DB::table('users')->where('role', 'hr')->update([
            'job_role_id' => DB::table('job_roles')->where('key', 'hr_recruiter')->value('id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_role_id');
        });

        Schema::dropIfExists('job_role_permissions');
        Schema::dropIfExists('job_roles');
    }
};

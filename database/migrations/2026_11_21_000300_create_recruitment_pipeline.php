<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Alur tahap rekrutmen yang dapat diatur. Tahap sistem (diundang, terdaftar,
     * diterima, tidak lolos, mengundurkan diri) tetap; di antaranya terdapat
     * tahap proses milik setiap rekrutmen, disalin dari alur default saat
     * rekrutmen dibuat.
     *
     * Data lama dipindahkan: "Ditinjau" dan "Lolos seleksi awal" menjadi dua
     * tahap proses pertama di alur default dan di setiap rekrutmen.
     */
    public function up(): void
    {
        Schema::create('pipeline_stage_templates', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
        });

        Schema::create('campaign_stages', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('recruitment_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['recruitment_campaign_id', 'position']);
        });

        Schema::table('recruitment_applications', function (Blueprint $table) {
            // Tahap proses saat ini, atau tahap terakhir sebelum hasil akhir.
            $table->foreignId('campaign_stage_id')->nullable()->after('stage')->constrained()->restrictOnDelete();
        });

        Schema::table('application_stage_histories', function (Blueprint $table) {
            // Nama tahap saat perubahan terjadi, agar riwayat tetap terbaca walau tahap diganti nama atau dihapus.
            $table->string('from_label', 100)->nullable()->after('to_stage');
            $table->string('to_label', 100)->nullable()->after('from_label');
        });

        $this->migrateExistingData();
    }

    private function migrateExistingData(): void
    {
        $now = now();
        $defaults = ['Ditinjau', 'Lolos seleksi awal'];

        foreach ($defaults as $index => $name) {
            DB::table('pipeline_stage_templates')->insert([
                'public_id' => strtolower((string) Str::ulid()),
                'name' => $name,
                'position' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('recruitment_campaigns')->pluck('id') as $campaignId) {
            $stageIds = [];

            foreach ($defaults as $index => $name) {
                $stageIds[$index] = DB::table('campaign_stages')->insertGetId([
                    'public_id' => strtolower((string) Str::ulid()),
                    'recruitment_campaign_id' => $campaignId,
                    'name' => $name,
                    'position' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach (['under_review' => 0, 'shortlisted' => 1] as $oldStage => $index) {
                DB::table('recruitment_applications')
                    ->where('recruitment_campaign_id', $campaignId)
                    ->where('stage', $oldStage)
                    ->update(['stage' => 'in_process', 'campaign_stage_id' => $stageIds[$index]]);
            }
        }

        $labels = [
            'invited' => 'Diundang',
            'registered' => 'Terdaftar',
            'under_review' => 'Ditinjau',
            'shortlisted' => 'Lolos seleksi awal',
            'rejected' => 'Tidak lolos',
            'hired' => 'Diterima',
            'withdrawn' => 'Mengundurkan diri',
        ];

        foreach ($labels as $stage => $label) {
            DB::table('application_stage_histories')->where('from_stage', $stage)->update(['from_label' => $label]);
            DB::table('application_stage_histories')->where('to_stage', $stage)->update(['to_label' => $label]);
        }

        DB::table('application_stage_histories')->whereIn('from_stage', ['under_review', 'shortlisted'])->update(['from_stage' => 'in_process']);
        DB::table('application_stage_histories')->whereIn('to_stage', ['under_review', 'shortlisted'])->update(['to_stage' => 'in_process']);
    }

    public function down(): void
    {
        Schema::table('application_stage_histories', function (Blueprint $table) {
            $table->dropColumn(['from_label', 'to_label']);
        });

        Schema::table('recruitment_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_stage_id');
        });

        Schema::dropIfExists('campaign_stages');
        Schema::dropIfExists('pipeline_stage_templates');
    }
};

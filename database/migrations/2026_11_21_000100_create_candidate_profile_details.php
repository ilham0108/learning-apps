<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data diri yang dilengkapi kandidat sendiri: identitas dasar, riwayat
     * pendidikan, pengalaman kerja, dan dokumen (CV, KTP, foto, sertifikat).
     * File disimpan di disk privat; tabel hanya menyimpan path dan metadata.
     */
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->after('last_education');
            $table->date('birth_date')->nullable()->after('city');
            $table->string('religion', 30)->nullable()->after('birth_date');
            $table->string('marital_status', 30)->nullable()->after('religion');
            $table->string('linkedin_url', 255)->nullable()->after('marital_status');
            $table->string('portfolio_url', 255)->nullable()->after('linkedin_url');
        });

        Schema::create('candidate_educations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('level', 20);
            $table->string('institution', 150);
            $table->string('major', 150)->nullable();
            $table->unsignedSmallInteger('start_year')->nullable();
            // Kosong = masih berjalan.
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->timestamps();

            $table->index('candidate_profile_id');
        });

        Schema::create('candidate_experiences', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('company', 150);
            $table->string('job_title', 150);
            // Disimpan sebagai tanggal 1 pada bulan tersebut.
            $table->date('started_on');
            // Kosong = masih bekerja di sini.
            $table->date('ended_on')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('candidate_profile_id');
        });

        Schema::create('candidate_documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('label', 150)->nullable();
            $table->string('file_path');
            $table->string('file_original_name', 255);
            $table->string('file_mime', 100);
            $table->unsignedInteger('file_size');
            $table->timestamps();

            $table->index(['candidate_profile_id', 'type']);
        });

        // Teks persetujuan berubah karena ada kategori data baru, sehingga kandidat
        // diminta menyetujui ulang versi terbaru saat masuk berikutnya.
        DB::table('system_settings')
            ->where('key', 'consent_policy_version')
            ->update(['value' => json_encode('2026-11')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_documents');
        Schema::dropIfExists('candidate_experiences');
        Schema::dropIfExists('candidate_educations');

        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn(['city', 'birth_date', 'religion', 'marital_status', 'linkedin_url', 'portfolio_url']);
        });
    }
};

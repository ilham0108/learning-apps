<?php

namespace App\Domain\Candidates\Actions;

use App\Domain\Candidates\Enums\CampaignStatus;
use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Domain\Shared\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Menambahkan kandidat ke gelombang. Email yang sudah terdaftar sebagai kandidat
 * mendapat lamaran baru, bukan akun baru (aturan B2). Akun baru belum punya
 * password yang diketahui siapa pun sampai kandidat memakai tautan aktivasi.
 */
class RegisterCandidate
{
    public function __construct(
        private readonly GenerateCandidateNumber $generateNumber,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, last_education?: string|null}  $data
     */
    public function __invoke(array $data, RecruitmentCampaign $campaign, User $actor): RecruitmentApplication
    {
        if ($campaign->status === CampaignStatus::Closed) {
            throw ValidationException::withMessages(['campaign' => __('Gelombang yang sudah ditutup tidak dapat menerima kandidat baru.')]);
        }

        return DB::transaction(function () use ($data, $campaign, $actor) {
            $email = Str::lower($data['email']);
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($user !== null && ! $user->isCandidate()) {
                throw ValidationException::withMessages(['email' => __('Email ini dipakai akun staf dan tidak dapat didaftarkan sebagai kandidat.')]);
            }

            $profile = $user->candidateProfile ?? $this->createCandidate($data, $email, $user);

            if ($profile->applications()->where('recruitment_campaign_id', $campaign->id)->exists()) {
                throw ValidationException::withMessages(['email' => __('Kandidat ini sudah terdaftar di gelombang tersebut.')]);
            }

            // Kandidat yang sudah aktif langsung "registered" di gelombang baru.
            $stage = $profile->activated_at !== null ? RecruitmentStage::Registered : RecruitmentStage::Invited;

            $application = $profile->applications()->create([
                'recruitment_campaign_id' => $campaign->id,
                'stage' => $stage,
                'stage_changed_at' => now(),
                'applied_at' => now(),
            ]);

            $application->stageHistories()->create([
                'from_stage' => null,
                'to_stage' => $stage,
                'changed_by' => $actor->id,
            ]);

            $this->logger->log('application.created', $application, ['campaign' => $campaign->public_id], actor: $actor);

            return $application;
        });
    }

    /**
     * @param  array{name: string, email: string, phone?: string|null, last_education?: string|null}  $data
     */
    private function createCandidate(array $data, string $email, ?User $user): CandidateProfile
    {
        $user ??= new User;
        $user->forceFill([
            'name' => $data['name'],
            'email' => $email,
            // Password acak yang tidak pernah ditampilkan; diganti saat aktivasi.
            'password' => Str::password(40),
            'role' => Role::Candidate,
            'is_active' => true,
        ])->save();

        return $user->candidateProfile()->create([
            'candidate_number' => ($this->generateNumber)(),
            'phone' => $data['phone'] ?? null,
            'last_education' => $data['last_education'] ?? null,
            'registered_on' => now()->toDateString(),
        ]);
    }
}

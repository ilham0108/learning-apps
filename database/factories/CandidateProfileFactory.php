<?php

namespace Database\Factories;

use App\Domain\Candidates\Enums\CandidateDocumentType;
use App\Domain\Candidates\Enums\EducationLevel;
use App\Domain\Candidates\Enums\MaritalStatus;
use App\Domain\Candidates\Enums\Religion;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateProfile>
 */
class CandidateProfileFactory extends Factory
{
    protected $model = CandidateProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->candidate(),
            'candidate_number' => sprintf('CAND-%d-%06d', now()->year, fake()->unique()->numberBetween(1, 999999)),
            'phone' => fake()->numerify('08##########'),
            'last_education' => fake()->randomElement(['SMA/SMK', 'D3', 'S1', 'S2']),
            'registered_on' => now()->toDateString(),
        ];
    }

    /**
     * Kandidat yang sudah mengaktifkan akun dan menyetujui kebijakan privasi.
     */
    public function activated(?string $policyVersion = null): static
    {
        return $this->state(fn (array $attributes) => [
            'activated_at' => now(),
            'consent_given_at' => now(),
            'consent_policy_version' => $policyVersion ?? config('recruitment.defaults.consent_policy_version'),
        ]);
    }

    /**
     * Data diri lengkap: identitas, satu riwayat pendidikan, serta CV, KTP, dan foto.
     * Baris dokumen menunjuk path palsu; file tidak dibuat di disk.
     */
    public function withCompleteProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'city' => fake()->city(),
            'birth_date' => fake()->dateTimeBetween('-40 years', '-20 years')->format('Y-m-d'),
            'religion' => fake()->randomElement(Religion::cases()),
            'marital_status' => fake()->randomElement(MaritalStatus::cases()),
        ])->afterCreating(function (CandidateProfile $profile): void {
            $profile->educations()->create([
                'level' => EducationLevel::S1,
                'institution' => 'Universitas '.fake()->city(),
                'major' => 'Manajemen',
                'start_year' => 2016,
                'graduation_year' => 2020,
            ]);

            foreach ([CandidateDocumentType::Cv, CandidateDocumentType::IdCard, CandidateDocumentType::Photo] as $type) {
                $profile->documents()->create([
                    'type' => $type,
                    'file_path' => 'candidates/'.$profile->public_id.'/'.$type->value.'.pdf',
                    'file_original_name' => $type->value.'.pdf',
                    'file_mime' => 'application/pdf',
                    'file_size' => 1024,
                ]);
            }
        });
    }
}

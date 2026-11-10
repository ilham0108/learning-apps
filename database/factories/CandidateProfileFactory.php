<?php

namespace Database\Factories;

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
    public function activated(string $policyVersion = '2026-10'): static
    {
        return $this->state(fn (array $attributes) => [
            'activated_at' => now(),
            'consent_given_at' => now(),
            'consent_policy_version' => $policyVersion,
        ]);
    }
}

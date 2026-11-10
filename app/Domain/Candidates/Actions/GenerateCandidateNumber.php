<?php

namespace App\Domain\Candidates\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Nomor kandidat `CAND-{tahun}-{6 digit}` yang unik dan berurutan per tahun (aturan B1).
 * Harus dipanggil di dalam transaction; baris counter dikunci agar aman dari race condition.
 */
class GenerateCandidateNumber
{
    public function __invoke(): string
    {
        $year = now()->year;

        DB::table('candidate_number_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

        $last = (int) DB::table('candidate_number_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->value('last_number');

        DB::table('candidate_number_sequences')
            ->where('year', $year)
            ->update(['last_number' => $last + 1]);

        return sprintf('CAND-%d-%06d', $year, $last + 1);
    }
}

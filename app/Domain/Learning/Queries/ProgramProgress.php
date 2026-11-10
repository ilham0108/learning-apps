<?php

namespace App\Domain\Learning\Queries;

use App\Domain\Learning\Enums\LearningProgressStatus;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung progres belajar dengan agregasi di database. Progres selalu dihitung
 * terhadap daftar materi wajib aktif saat ini, sehingga materi wajib baru tidak
 * menghapus progres lama tetapi menurunkan persentase (rules Bagian 6).
 */
class ProgramProgress
{
    /**
     * Progres satu kandidat untuk beberapa program.
     *
     * @param  array<int, int>  $programIds
     * @return array<int, ProgressSummary> Kunci: ID program.
     */
    public function forCandidate(int $candidateProfileId, array $programIds): array
    {
        if ($programIds === []) {
            return [];
        }

        $rows = DB::table('learning_materials as m')
            ->join('learning_modules as mod', 'mod.id', '=', 'm.learning_module_id')
            ->leftJoin('material_progress as p', fn ($join) => $join
                ->on('p.learning_material_id', '=', 'm.id')
                ->where('p.candidate_profile_id', $candidateProfileId))
            ->whereIn('mod.learning_program_id', $programIds)
            ->whereNull('m.archived_at')
            ->groupBy('mod.learning_program_id')
            ->selectRaw('mod.learning_program_id as program_id')
            ->selectRaw('sum(case when m.is_required = 1 then 1 else 0 end) as required_total')
            ->selectRaw('sum(case when m.is_required = 1 and p.completed_at is not null then 1 else 0 end) as required_completed')
            ->selectRaw('sum(case when p.first_opened_at is not null or p.completed_at is not null then 1 else 0 end) as touched')
            ->get()
            ->keyBy('program_id');

        $result = [];

        foreach ($programIds as $programId) {
            $row = $rows->get($programId);
            $result[$programId] = new ProgressSummary(
                (int) ($row->required_total ?? 0),
                (int) ($row->required_completed ?? 0),
                (int) ($row->touched ?? 0) > 0,
            );
        }

        return $result;
    }

    /**
     * Progres semua kandidat untuk satu program.
     *
     * @param  array<int, int>  $candidateProfileIds
     * @return array<int, ProgressSummary> Kunci: ID profil kandidat.
     */
    public function forProgram(int $programId, array $candidateProfileIds): array
    {
        if ($candidateProfileIds === []) {
            return [];
        }

        $requiredTotal = DB::table('learning_materials as m')
            ->join('learning_modules as mod', 'mod.id', '=', 'm.learning_module_id')
            ->where('mod.learning_program_id', $programId)
            ->whereNull('m.archived_at')
            ->where('m.is_required', true)
            ->count();

        $rows = DB::table('material_progress as p')
            ->join('learning_materials as m', 'm.id', '=', 'p.learning_material_id')
            ->join('learning_modules as mod', 'mod.id', '=', 'm.learning_module_id')
            ->where('mod.learning_program_id', $programId)
            ->whereNull('m.archived_at')
            ->whereIn('p.candidate_profile_id', $candidateProfileIds)
            ->groupBy('p.candidate_profile_id')
            ->selectRaw('p.candidate_profile_id')
            ->selectRaw('sum(case when m.is_required = 1 and p.completed_at is not null then 1 else 0 end) as required_completed')
            ->selectRaw('count(*) as touched')
            ->get()
            ->keyBy('candidate_profile_id');

        $result = [];

        foreach ($candidateProfileIds as $profileId) {
            $row = $rows->get($profileId);
            $result[$profileId] = new ProgressSummary(
                $requiredTotal,
                (int) ($row->required_completed ?? 0),
                (int) ($row->touched ?? 0) > 0,
            );
        }

        return $result;
    }

    /**
     * Apakah kandidat sudah menyelesaikan semua materi wajib aktif dari program-program ini
     * (dipakai untuk prasyarat ujian).
     *
     * @param  array<int, int>  $programIds
     */
    public function hasCompletedRequired(int $candidateProfileId, array $programIds): bool
    {
        foreach ($this->forCandidate($candidateProfileId, $programIds) as $summary) {
            if ($summary->status() !== LearningProgressStatus::Completed) {
                return false;
            }
        }

        return true;
    }
}

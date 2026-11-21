<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportType;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Satu jenis laporan. Query yang sama dipakai untuk pratinjau di halaman dan
 * untuk file ekspor, sehingga isi keduanya selalu sama. Semua penghitungan
 * dilakukan dengan agregasi di database dan membaca nilai yang sudah tersimpan
 * (risiko R15), bukan menghitung ulang nilai di PHP.
 */
abstract class Report
{
    abstract public function type(): ReportType;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * Kolom laporan: kunci => judul kolom.
     *
     * @return array<string, string>
     */
    abstract public function columns(): array;

    /**
     * @param  Builder  $applications  ID lamaran dalam lingkup (ApplicationScope).
     * @param  array{program_id?: int|null, exam_id?: int|null}  $filters
     */
    abstract public function query(Builder $applications, array $filters): Builder;

    /**
     * Satu baris hasil query menjadi nilai per kolom, dalam urutan `columns()`.
     *
     * @return array<string, string|int|null>
     */
    abstract public function row(object $row): array;

    /**
     * Filter tambahan yang relevan untuk laporan ini.
     *
     * @return array<int, 'program'|'exam'>
     */
    public function extraFilters(): array
    {
        return [];
    }

    /**
     * Lamaran beserta identitas kandidat, rekrutmen, dan posisinya.
     */
    protected function applications(Builder $applications): Builder
    {
        return DB::table('recruitment_applications as a')
            ->join('candidate_profiles as cp', 'cp.id', '=', 'a.candidate_profile_id')
            ->join('users as u', 'u.id', '=', 'cp.user_id')
            ->join('recruitment_campaigns as c', 'c.id', '=', 'a.recruitment_campaign_id')
            ->join('positions as pos', 'pos.id', '=', 'c.position_id')
            ->whereIn('a.id', $applications);
    }

    /**
     * Subquery jumlah materi aktif program pada penugasan `pa`, untuk kandidat
     * pada lamaran `a`. Referensi ke tabel luar sengaja diletakkan di WHERE,
     * karena MySQL 5.7 menolaknya di klausa ON subquery berkorelasi.
     *
     * @param  bool  $requiredOnly  Hanya materi wajib.
     * @param  string|null  $progress  `completed` = sudah selesai, `touched` = pernah dibuka/selesai, null = semua materi.
     */
    protected function materialCount(bool $requiredOnly, ?string $progress): string
    {
        $sql = '(select count(*) from learning_modules mod_r'
            .' join learning_materials m_r on m_r.learning_module_id = mod_r.id';

        if ($progress !== null) {
            $sql .= ' join material_progress p_r on p_r.learning_material_id = m_r.id';
        }

        $sql .= ' where mod_r.learning_program_id = pa.learning_program_id and m_r.archived_at is null';
        $sql .= $requiredOnly ? ' and m_r.is_required = 1' : '';
        $sql .= match ($progress) {
            'completed' => ' and p_r.candidate_profile_id = a.candidate_profile_id and p_r.completed_at is not null',
            'touched' => ' and p_r.candidate_profile_id = a.candidate_profile_id and (p_r.first_opened_at is not null or p_r.completed_at is not null)',
            default => '',
        };

        return $sql.')';
    }
}

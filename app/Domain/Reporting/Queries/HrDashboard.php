<?php

namespace App\Domain\Reporting\Queries;

use App\Domain\Exams\Enums\AttemptResult;
use App\Domain\Exams\Enums\AttemptStatus;
use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Enums\GradingStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Angka dashboard HR (rules Bagian 11). Semua angka dihitung dengan agregasi di
 * database terhadap lamaran dalam lingkup pengguna setelah filter diterapkan;
 * tidak ada perulangan data di PHP.
 *
 * Satuan "kandidat" dihitung unik per profil kandidat. Hasil ujian dihitung per
 * penugasan ujian memakai attempt efektif (kebijakan nilai tertinggi/terakhir).
 */
class HrDashboard
{
    public const NEW_CANDIDATE_DAYS = 7;

    public const CLOSING_SOON_DAYS = 7;

    /**
     * @param  array{period_days?: int|null, campaign_id?: int|null, position_id?: int|null, department_id?: int|null, program_id?: int|null, stage?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function for(User $user, array $filters): array
    {
        $applications = ApplicationScope::ids($user, $filters);

        return [
            'stats' => [
                ...$this->candidateStats($applications),
                ...$this->learningStats($applications, $filters['program_id'] ?? null),
                ...$this->examStats($applications),
            ],
            'closingExams' => $this->closingExams($applications),
            'activity' => $this->recentActivity($applications),
        ];
    }

    /**
     * @return array{total_candidates: int, new_candidates: int}
     */
    private function candidateStats(Builder $applications): array
    {
        $row = DB::table('recruitment_applications as a')
            ->whereIn('a.id', $applications)
            ->selectRaw('count(distinct a.candidate_profile_id) as total')
            ->selectRaw('count(distinct case when a.applied_at >= ? then a.candidate_profile_id end) as recent', [now()->subDays(self::NEW_CANDIDATE_DAYS)])
            ->first();

        return [
            'total_candidates' => (int) $row->total,
            'new_candidates' => (int) $row->recent,
        ];
    }

    /**
     * Progres per penugasan program dihitung terhadap materi wajib aktif, lalu
     * dirangkum: kandidat yang sedang belajar dan persentase materi wajib selesai.
     *
     * @return array{learning_candidates: int, material_completion_percent: int|null}
     */
    private function learningStats(Builder $applications, ?int $programId): array
    {
        $perAssignment = DB::table('program_assignments as pa')
            ->join('recruitment_applications as a', 'a.id', '=', 'pa.recruitment_application_id')
            ->join('learning_modules as mod', 'mod.learning_program_id', '=', 'pa.learning_program_id')
            ->join('learning_materials as m', 'm.learning_module_id', '=', 'mod.id')
            ->leftJoin('material_progress as p', fn ($join) => $join
                ->on('p.learning_material_id', '=', 'm.id')
                ->on('p.candidate_profile_id', '=', 'a.candidate_profile_id'))
            ->whereIn('pa.recruitment_application_id', $applications)
            ->when($programId, fn ($q, int $id) => $q->where('pa.learning_program_id', $id))
            ->whereNull('m.archived_at')
            ->groupBy('pa.id', 'a.candidate_profile_id')
            ->selectRaw('a.candidate_profile_id as profile_id')
            ->selectRaw('sum(case when m.is_required = 1 then 1 else 0 end) as required_total')
            ->selectRaw('sum(case when m.is_required = 1 and p.completed_at is not null then 1 else 0 end) as required_completed')
            ->selectRaw('sum(case when p.first_opened_at is not null or p.completed_at is not null then 1 else 0 end) as touched');

        $row = DB::query()->fromSub($perAssignment, 'x')
            ->selectRaw('count(distinct case when x.touched > 0 and x.required_completed < x.required_total then x.profile_id end) as learning')
            ->selectRaw('sum(x.required_total) as required_total')
            ->selectRaw('sum(x.required_completed) as required_completed')
            ->first();

        $total = (int) ($row->required_total ?? 0);

        return [
            'learning_candidates' => (int) ($row->learning ?? 0),
            'material_completion_percent' => $total === 0 ? null : (int) floor((int) $row->required_completed / $total * 100),
        ];
    }

    /**
     * @return array{in_exam_candidates: int, passed: int, failed: int, pending_grading: int, average_score: string|null}
     */
    private function examStats(Builder $applications): array
    {
        $inExam = DB::table('exam_attempts as t')
            ->join('exam_assignments as ea', 'ea.id', '=', 't.exam_assignment_id')
            ->join('recruitment_applications as a', 'a.id', '=', 'ea.recruitment_application_id')
            ->whereIn('ea.recruitment_application_id', $applications)
            ->where('t.status', AttemptStatus::InProgress->value)
            ->where('t.deadline_at', '>', now())
            ->distinct()
            ->count('a.candidate_profile_id');

        $pending = DB::table('exam_attempts as t')
            ->join('exam_assignments as ea', 'ea.id', '=', 't.exam_assignment_id')
            ->whereIn('ea.recruitment_application_id', $applications)
            ->where('t.grading_status', GradingStatus::PendingManual->value)
            ->count();

        $results = DB::table('exam_assignments as ea')
            ->join('exam_attempts as t', 't.id', '=', 'ea.effective_attempt_id')
            ->whereIn('ea.recruitment_application_id', $applications)
            ->selectRaw('sum(case when t.result = ? then 1 else 0 end) as passed', [AttemptResult::Passed->value])
            ->selectRaw('sum(case when t.result = ? then 1 else 0 end) as failed', [AttemptResult::Failed->value])
            ->selectRaw('avg(case when t.grading_status = ? then t.score_percent end) as average', [GradingStatus::Graded->value])
            ->first();

        return [
            'in_exam_candidates' => $inExam,
            'passed' => (int) ($results->passed ?? 0),
            'failed' => (int) ($results->failed ?? 0),
            'pending_grading' => $pending,
            'average_score' => $results->average === null ? null : number_format((float) $results->average, 2, '.', ''),
        ];
    }

    /**
     * Ujian terbit dengan waktu tutup efektif (termasuk jadwal khusus) dalam 7 hari ke depan.
     *
     * @return array<int, array{id: string, title: string, closes_at: string, assigned: int, not_started: int}>
     */
    private function closingExams(Builder $applications): array
    {
        $closesAt = 'coalesce(ea.closes_at_override, e.closes_at)';

        return DB::table('exam_assignments as ea')
            ->join('exams as e', 'e.id', '=', 'ea.exam_id')
            ->whereIn('ea.recruitment_application_id', $applications)
            ->where('e.status', ExamStatus::Published->value)
            ->whereRaw("{$closesAt} > ?", [now()])
            ->whereRaw("{$closesAt} <= ?", [now()->addDays(self::CLOSING_SOON_DAYS)])
            ->groupBy('e.id', 'e.public_id', 'e.title')
            ->selectRaw('e.public_id, e.title')
            ->selectRaw("min({$closesAt}) as closes_at")
            ->selectRaw('count(*) as assigned')
            ->selectRaw('sum(case when not exists (select 1 from exam_attempts t where t.exam_assignment_id = ea.id) then 1 else 0 end) as not_started')
            ->orderBy('closes_at')
            ->limit(10)
            ->get()
            ->map(fn (object $row) => [
                'id' => $row->public_id,
                'title' => $row->title,
                'closes_at' => CarbonImmutable::parse($row->closes_at, 'UTC')->toIso8601String(),
                'assigned' => (int) $row->assigned,
                'not_started' => (int) $row->not_started,
            ])
            ->all();
    }

    /**
     * Peristiwa terbaru: perubahan tahap, ujian dikumpulkan, dan materi wajib selesai.
     *
     * @return array<int, array{type: string, candidate: array{id: string, name: string}, detail: string|null, occurred_at: string}>
     */
    private function recentActivity(Builder $applications): array
    {
        $base = fn () => DB::table('recruitment_applications as a')
            ->join('candidate_profiles as cp', 'cp.id', '=', 'a.candidate_profile_id')
            ->join('users as u', 'u.id', '=', 'cp.user_id')
            ->whereIn('a.id', $applications);

        $stages = $base()
            ->join('application_stage_histories as h', 'h.recruitment_application_id', '=', 'a.id')
            ->selectRaw("'stage_changed' as type, u.name as candidate, cp.public_id as candidate_public_id, h.to_stage as detail, h.created_at as occurred_at");

        $attempts = $base()
            ->join('exam_assignments as ea', 'ea.recruitment_application_id', '=', 'a.id')
            ->join('exams as e', 'e.id', '=', 'ea.exam_id')
            ->join('exam_attempts as t', 't.exam_assignment_id', '=', 'ea.id')
            ->whereNotNull('t.submitted_at')
            ->selectRaw("'exam_submitted' as type, u.name as candidate, cp.public_id as candidate_public_id, e.title as detail, t.submitted_at as occurred_at");

        $materials = $base()
            ->join('program_assignments as pa', 'pa.recruitment_application_id', '=', 'a.id')
            ->join('learning_programs as lp', 'lp.id', '=', 'pa.learning_program_id')
            ->join('learning_modules as mod', 'mod.learning_program_id', '=', 'lp.id')
            ->join('learning_materials as m', 'm.learning_module_id', '=', 'mod.id')
            ->join('material_progress as p', fn ($join) => $join
                ->on('p.learning_material_id', '=', 'm.id')
                ->on('p.candidate_profile_id', '=', 'a.candidate_profile_id'))
            ->whereNotNull('p.completed_at')
            ->selectRaw("'material_completed' as type, u.name as candidate, cp.public_id as candidate_public_id, m.title as detail, p.completed_at as occurred_at");

        return DB::query()
            ->fromSub($stages->unionAll($attempts)->unionAll($materials), 'events')
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get()
            ->map(fn (object $row) => [
                'type' => $row->type,
                'candidate' => ['id' => $row->candidate_public_id, 'name' => $row->candidate],
                'detail' => $row->detail,
                'occurred_at' => CarbonImmutable::parse($row->occurred_at, 'UTC')->toIso8601String(),
            ])
            ->all();
    }
}

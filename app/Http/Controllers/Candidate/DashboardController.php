<?php

namespace App\Http\Controllers\Candidate;

use App\Domain\Candidates\Enums\EvaluationStatus;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Support\ProfileCompleteness;
use App\Domain\Exams\Queries\CandidateExams;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Queries\ProgramProgress;
use App\Http\Controllers\Controller;
use App\Http\Resources\Candidates\OwnApplicationResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda kandidat (rules Bagian 11): lamaran dan tahapnya, status evaluasi,
 * progres program, materi wajib berikutnya, ujian, dan nilai yang sudah
 * dipublikasikan. `/dashboard` juga menjadi tujuan setelah login, sehingga
 * Super Admin dan HR diarahkan ke beranda administrasi.
 */
class DashboardController extends Controller
{
    private const NEXT_MATERIALS = 5;

    public function __invoke(Request $request, ProgramProgress $progress, CandidateExams $exams): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isCandidate()) {
            return to_route('admin.dashboard');
        }

        $profile = $user->candidateProfile;

        $applications = RecruitmentApplication::query()
            ->visibleTo($user)
            ->with('campaign.position.department')
            ->withCount([
                'evaluations',
                'evaluations as final_evaluations_count' => fn ($query) => $query->where('status', EvaluationStatus::Final),
            ])
            ->latest('applied_at')
            ->get();

        $programs = LearningProgram::query()->assignedToCandidate($user)->orderBy('name')->get();
        $summaries = $progress->forCandidate($profile->id, $programs->map(fn (LearningProgram $program) => $program->id)->all());

        return Inertia::render('candidate/dashboard', [
            'candidateNumber' => $profile->candidate_number,
            'applications' => $applications->map(fn (RecruitmentApplication $application) => [
                ...OwnApplicationResource::make($application)->resolve($request),
                // Kandidat hanya melihat status evaluasi, bukan isi penilaian internal.
                'evaluation_status' => match (true) {
                    $application->final_evaluations_count > 0 => 'final',
                    $application->evaluations_count > 0 => 'in_progress',
                    default => 'pending',
                },
            ])->all(),
            'programs' => $programs->map(fn (LearningProgram $program) => [
                'id' => $program->public_id,
                'name' => $program->name,
                'progress' => $summaries[$program->id]->toArray(),
            ])->all(),
            'nextMaterials' => $this->nextMaterials($profile->id, $programs->map(fn (LearningProgram $program) => $program->id)->all()),
            'exams' => $exams->rows($user),
            'results' => $exams->publishedResults($user),
            'profileCompleteness' => ProfileCompleteness::check($profile),
            // Hitung mundur jadwal tutup memakai jam server, bukan jam perangkat.
            'serverNow' => now()->format('Y-m-d\TH:i:s.vP'),
        ]);
    }

    /**
     * Materi wajib aktif yang belum selesai, berurutan sesuai susunan program.
     *
     * @param  array<int, int>  $programIds
     * @return array<int, array{id: string, title: string, type: string, program: array{id: string, name: string}}>
     */
    private function nextMaterials(int $profileId, array $programIds): array
    {
        if ($programIds === []) {
            return [];
        }

        return DB::table('learning_materials as m')
            ->join('learning_modules as mod', 'mod.id', '=', 'm.learning_module_id')
            ->join('learning_programs as lp', 'lp.id', '=', 'mod.learning_program_id')
            ->leftJoin('material_progress as p', fn ($join) => $join
                ->on('p.learning_material_id', '=', 'm.id')
                ->where('p.candidate_profile_id', $profileId))
            ->whereIn('lp.id', $programIds)
            ->whereNull('m.archived_at')
            ->where('m.is_required', true)
            ->whereNull('p.completed_at')
            ->orderBy('lp.name')
            ->orderBy('mod.position')
            ->orderBy('m.position')
            ->limit(self::NEXT_MATERIALS)
            ->get(['m.public_id', 'm.title', 'm.type', 'lp.public_id as program_public_id', 'lp.name as program_name'])
            ->map(fn (object $row) => [
                'id' => $row->public_id,
                'title' => $row->title,
                'type' => $row->type,
                'program' => ['id' => $row->program_public_id, 'name' => $row->program_name],
            ])
            ->all();
    }
}

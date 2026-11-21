<?php

namespace Tests\Feature;

use App\Domain\Candidates\Models\CandidateEvaluation;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Exams\Enums\ResultRelease;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\LearningModule;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Models\MaterialProgress;
use App\Domain\Learning\Models\ProgramAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsNoInternalIds;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use AssertsNoInternalIds, RefreshDatabase;

    public function test_the_root_url_redirects_to_the_dashboard()
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_super_admin_and_hr_are_sent_to_the_admin_dashboard()
    {
        foreach ([User::factory()->superAdmin()->create(), User::factory()->hr()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertRedirect(route('admin.dashboard'));
        }
    }

    /**
     * Program terbit dengan dua materi wajib dan satu opsional.
     *
     * @return array{0: LearningProgram, 1: array<int, LearningMaterial>}
     */
    private function program(): array
    {
        $program = LearningProgram::factory()->published()->create();
        $module = LearningModule::factory()->for($program, 'program')->create(['position' => 1]);

        return [$program, [
            LearningMaterial::factory()->for($module, 'module')->create(['position' => 1]),
            LearningMaterial::factory()->for($module, 'module')->create(['position' => 2]),
            LearningMaterial::factory()->for($module, 'module')->optional()->create(['position' => 3]),
        ]];
    }

    private function complete(CandidateProfile $profile, LearningMaterial $material): void
    {
        MaterialProgress::query()->create([
            'candidate_profile_id' => $profile->id,
            'learning_material_id' => $material->id,
            'first_opened_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Attempt final yang menjadi nilai efektif penugasan.
     */
    private function gradedAttempt(ExamAssignment $assignment, float $score): ExamAttempt
    {
        $attempt = ExamAttempt::factory()->graded($score)->create(['exam_assignment_id' => $assignment->id]);
        $assignment->forceFill(['effective_attempt_id' => $attempt->id])->save();

        return $attempt;
    }

    public function test_hr_dashboard_counts_only_applications_in_scope()
    {
        $hr = User::factory()->hr()->create();
        $campaign = RecruitmentCampaign::factory()->assignedTo($hr)->create();
        [$program, $materials] = $this->program();

        // Kandidat A: sedang belajar (1 dari 2 materi wajib), lulus ujian.
        $a = RecruitmentApplication::factory()->for($campaign, 'campaign')->create();
        ProgramAssignment::factory()->create(['learning_program_id' => $program->id, 'recruitment_application_id' => $a->id]);
        $this->complete($a->candidateProfile, $materials[0]);
        $exam = Exam::factory()->published()->create(['closes_at' => now()->addDays(3)]);
        $this->gradedAttempt(ExamAssignment::factory()->create(['exam_id' => $exam->id, 'recruitment_application_id' => $a->id]), 90);

        // Kandidat B: semua materi wajib selesai, gagal ujian, satu attempt menunggu penilaian.
        $b = RecruitmentApplication::factory()->for($campaign, 'campaign')->create(['applied_at' => now()->subDays(20)]);
        ProgramAssignment::factory()->create(['learning_program_id' => $program->id, 'recruitment_application_id' => $b->id]);
        $this->complete($b->candidateProfile, $materials[0]);
        $this->complete($b->candidateProfile, $materials[1]);
        $assignmentB = ExamAssignment::factory()->create(['exam_id' => $exam->id, 'recruitment_application_id' => $b->id]);
        $this->gradedAttempt($assignmentB, 50);
        ExamAttempt::factory()->submitted()->create(['exam_assignment_id' => $assignmentB->id, 'attempt_number' => 2]);

        // Kandidat C: sedang mengerjakan ujian lain; belum mulai ujian yang akan ditutup.
        $c = RecruitmentApplication::factory()->for($campaign, 'campaign')->create();
        ExamAttempt::factory()->create(['exam_assignment_id' => ExamAssignment::factory()->create(['recruitment_application_id' => $c->id])->id]);
        ExamAssignment::factory()->create(['exam_id' => $exam->id, 'recruitment_application_id' => $c->id]);

        // Di luar lingkup HR: tidak boleh ikut terhitung.
        $outside = RecruitmentApplication::factory()->create();
        $this->gradedAttempt(ExamAssignment::factory()->create(['exam_id' => $exam->id, 'recruitment_application_id' => $outside->id]), 100);

        $response = $this->actingAs($hr)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/dashboard')
                ->where('stats.total_candidates', 3)
                ->where('stats.new_candidates', 2)
                ->where('stats.learning_candidates', 1)
                // 3 dari 4 materi wajib selesai.
                ->where('stats.material_completion_percent', 75)
                ->where('stats.in_exam_candidates', 1)
                ->where('stats.passed', 1)
                ->where('stats.failed', 1)
                ->where('stats.pending_grading', 1)
                ->where('stats.average_score', '70.00')
                ->has('closingExams', 1)
                ->where('closingExams.0.id', $exam->public_id)
                ->where('closingExams.0.assigned', 3)
                ->where('closingExams.0.not_started', 1)
                ->has('options.campaigns', 1));

        $this->assertResponseHasNoInternalIds($response);
    }

    public function test_filters_narrow_the_numbers_and_reject_campaigns_outside_scope()
    {
        $hr = User::factory()->hr()->create();
        $first = RecruitmentCampaign::factory()->assignedTo($hr)->create();
        $second = RecruitmentCampaign::factory()->assignedTo($hr)->create();
        RecruitmentApplication::factory()->count(2)->for($first, 'campaign')->create();
        RecruitmentApplication::factory()->for($second, 'campaign')->inProcess()->create();
        RecruitmentApplication::factory()->for($second, 'campaign')->create(['applied_at' => now()->subDays(60)]);

        $this->actingAs($hr);
        $this->get(route('admin.dashboard', ['campaign' => $first->public_id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total_candidates', 2)
                ->where('filters.campaign', $first->public_id));
        $this->get(route('admin.dashboard', ['stage' => 'in_process']))
            ->assertInertia(fn (Assert $page) => $page->where('stats.total_candidates', 1));
        $this->get(route('admin.dashboard', ['period' => 30]))
            ->assertInertia(fn (Assert $page) => $page->where('stats.total_candidates', 3));
        $this->get(route('admin.dashboard', ['position' => $second->position->public_id]))
            ->assertInertia(fn (Assert $page) => $page->where('stats.total_candidates', 2));
        $this->get(route('admin.dashboard', ['department' => $first->position->department->public_id]))
            ->assertInertia(fn (Assert $page) => $page->where('stats.total_candidates', 2));

        $this->get(route('admin.dashboard', ['campaign' => RecruitmentCampaign::factory()->create()->public_id]))
            ->assertSessionHasErrors('campaign');
        $this->get(route('admin.dashboard', ['period' => 45]))->assertSessionHasErrors('period');
    }

    public function test_program_filter_limits_candidates_and_material_completion()
    {
        $hr = User::factory()->hr()->create();
        $campaign = RecruitmentCampaign::factory()->assignedTo($hr)->create();
        [$program, $materials] = $this->program();
        [$other] = $this->program();
        $assigned = RecruitmentApplication::factory()->for($campaign, 'campaign')->create();
        ProgramAssignment::factory()->create(['learning_program_id' => $program->id, 'recruitment_application_id' => $assigned->id]);
        $this->complete($assigned->candidateProfile, $materials[0]);
        $this->complete($assigned->candidateProfile, $materials[1]);
        $notAssigned = RecruitmentApplication::factory()->for($campaign, 'campaign')->create();
        ProgramAssignment::factory()->create(['learning_program_id' => $other->id, 'recruitment_application_id' => $notAssigned->id]);

        $this->actingAs($hr)->get(route('admin.dashboard', ['program' => $program->public_id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total_candidates', 1)
                ->where('stats.material_completion_percent', 100));
    }

    public function test_recent_activity_lists_stage_changes_and_submissions()
    {
        $hr = User::factory()->hr()->create();
        $campaign = RecruitmentCampaign::factory()->assignedTo($hr)->create();
        $application = RecruitmentApplication::factory()->for($campaign, 'campaign')->create();
        $application->stageHistories()->create(['from_stage' => 'invited', 'to_stage' => 'registered', 'from_label' => 'Diundang', 'to_label' => 'Terdaftar', 'created_at' => now()->subHour()]);
        $exam = Exam::factory()->published()->create(['title' => 'Tes Produk']);
        ExamAttempt::factory()->graded()->create([
            'exam_assignment_id' => ExamAssignment::factory()->create(['exam_id' => $exam->id, 'recruitment_application_id' => $application->id])->id,
        ]);

        $this->actingAs($hr)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activity.0.type', 'exam_submitted')
                ->where('activity.0.detail', 'Tes Produk')
                ->where('activity.0.candidate.id', $application->candidateProfile->public_id)
                ->where('activity.1.type', 'stage_changed')
                ->where('activity.1.detail', 'Terdaftar'));
    }

    public function test_candidates_cannot_open_the_hr_dashboard()
    {
        $this->actingAs(CandidateProfile::factory()->activated()->create()->user)
            ->get(route('admin.dashboard'))
            ->assertNotFound();
    }

    public function test_candidate_dashboard_shows_own_progress_exams_and_published_results()
    {
        $profile = CandidateProfile::factory()->activated()->create();
        $application = RecruitmentApplication::factory()->for($profile)->create();
        RecruitmentApplication::factory()->create();
        CandidateEvaluation::factory()->create(['recruitment_application_id' => $application->id]);
        [$program, $materials] = $this->program();
        ProgramAssignment::factory()->create(['learning_program_id' => $program->id, 'recruitment_application_id' => $application->id]);
        $this->complete($profile, $materials[0]);

        $open = Exam::factory()->published()->create(['title' => 'Tes Terbuka']);
        ExamAssignment::factory()->create(['exam_id' => $open->id, 'recruitment_application_id' => $application->id]);
        $released = ExamAssignment::factory()->create([
            'exam_id' => Exam::factory()->published()->create(['title' => 'Tes Selesai'])->id,
            'recruitment_application_id' => $application->id,
        ]);
        ExamAttempt::factory()->graded(85)->create(['exam_assignment_id' => $released->id]);
        $hidden = ExamAssignment::factory()->create([
            'exam_id' => Exam::factory()->published()->create(['result_release' => ResultRelease::Manual])->id,
            'recruitment_application_id' => $application->id,
        ]);
        ExamAttempt::factory()->graded(40)->create(['exam_assignment_id' => $hidden->id, 'result_release' => ResultRelease::Manual]);

        $response = $this->actingAs($profile->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/dashboard')
                ->where('candidateNumber', $profile->candidate_number)
                ->has('applications', 1)
                ->where('applications.0.evaluation_status', 'in_progress')
                ->has('programs', 1)
                ->where('programs.0.progress.percent', 50)
                // Hanya materi wajib yang belum selesai.
                ->has('nextMaterials', 1)
                ->where('nextMaterials.0.id', $materials[1]->public_id)
                ->has('exams', 3)
                // Nilai yang menunggu publikasi manual tidak ikut.
                ->has('results', 1)
                ->where('results.0.exam', 'Tes Selesai')
                ->where('results.0.score_percent', '85.00')
                ->has('serverNow')
                ->where('exams.0.active_attempt', null));

        $this->assertResponseHasNoInternalIds($response);
        $this->assertStringNotContainsString('40.00', json_encode($response->inertiaProps()));
    }

    public function test_candidate_sees_final_evaluation_status_without_its_content()
    {
        $profile = CandidateProfile::factory()->activated()->create();
        $application = RecruitmentApplication::factory()->for($profile)->create();
        CandidateEvaluation::factory()->finalized()->create([
            'recruitment_application_id' => $application->id,
            'summary' => 'Catatan internal rahasia',
        ]);

        $response = $this->actingAs($profile->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('applications.0.evaluation_status', 'final'));

        $this->assertStringNotContainsString('Catatan internal rahasia', $response->getContent());
    }
}

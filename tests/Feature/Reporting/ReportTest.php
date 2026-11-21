<?php

namespace Tests\Feature\Reporting;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\LearningModule;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Models\MaterialProgress;
use App\Domain\Learning\Models\ProgramAssignment;
use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Jobs\GenerateReportExport;
use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Support\CsvWriter;
use App\Domain\Shared\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsNoInternalIds;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use AssertsNoInternalIds, RefreshDatabase;

    private User $hr;

    private RecruitmentCampaign $campaign;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(GenerateReportExport::DISK);
        $this->hr = User::factory()->hr()->create();
        $this->campaign = RecruitmentCampaign::factory()->assignedTo($this->hr)->create();
        $this->exam = Exam::factory()->published()->create(['title' => 'Tes Produk']);
    }

    /**
     * Lamaran di rekrutmen milik HR dengan satu penugasan ujian.
     */
    private function applicant(string $name, ?float $score = null, ?RecruitmentCampaign $campaign = null): RecruitmentApplication
    {
        $profile = CandidateProfile::factory()->activated()->create();
        $profile->user->update(['name' => $name]);
        $application = RecruitmentApplication::factory()->for($profile)->for($campaign ?? $this->campaign, 'campaign')->create();
        $assignment = ExamAssignment::factory()->create(['exam_id' => $this->exam->id, 'recruitment_application_id' => $application->id]);

        if ($score !== null) {
            $attempt = ExamAttempt::factory()->graded($score)->create(['exam_assignment_id' => $assignment->id]);
            $assignment->forceFill(['effective_attempt_id' => $attempt->id])->save();
        }

        return $application;
    }

    public function test_every_report_renders_within_the_hr_scope_without_internal_ids()
    {
        $this->applicant('Kandidat Dalam', 85);
        $this->applicant('Kandidat Luar', 95, RecruitmentCampaign::factory()->create());

        $this->actingAs($this->hr);

        foreach (ReportType::cases() as $type) {
            $response = $this->get(route('admin.reports.index', ['report' => $type->value]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('admin/reports/index')
                    ->where('report', $type->value)
                    ->has('reports', count(ReportType::cases())));

            $this->assertResponseHasNoInternalIds($response);
            $this->assertStringNotContainsString('Kandidat Luar', $response->getContent(), $type->value);
        }

        $this->get(route('admin.reports.index', ['report' => 'exam_results']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.meta.total', 1)
                ->where('rows.data.0.name', 'Kandidat Dalam')
                ->where('rows.data.0.score_percent', '85.00')
                ->where('rows.data.0.result', 'Lulus'));
    }

    public function test_pass_rate_counts_only_final_results()
    {
        $this->applicant('Lulus', 80);
        $this->applicant('Gagal', 40);
        $this->applicant('Belum');

        $this->actingAs($this->hr)->get(route('admin.reports.index', ['report' => 'pass_rate']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.0.exam', 'Tes Produk')
                ->where('rows.data.0.assigned', 3)
                ->where('rows.data.0.attempted', 2)
                ->where('rows.data.0.passed', 1)
                ->where('rows.data.0.failed', 1)
                ->where('rows.data.0.pass_rate', '50.00')
                ->where('rows.data.0.average_score', '60.00'));

        $this->get(route('admin.reports.index', ['report' => 'not_attempted']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.meta.total', 1)
                ->where('rows.data.0.name', 'Belum'));
    }

    public function test_learning_reports_count_required_materials()
    {
        $program = LearningProgram::factory()->published()->create(['name' => 'Onboarding']);
        $module = LearningModule::factory()->for($program, 'program')->create();
        $required = LearningMaterial::factory()->count(2)->for($module, 'module')->create();
        LearningMaterial::factory()->optional()->for($module, 'module')->create();

        $done = $this->applicant('Sudah Selesai');
        $halfway = $this->applicant('Setengah Jalan');

        foreach ([$done, $halfway] as $application) {
            ProgramAssignment::factory()->create(['learning_program_id' => $program->id, 'recruitment_application_id' => $application->id]);
        }

        foreach ([[$done, $required[0]], [$done, $required[1]], [$halfway, $required[0]]] as [$application, $material]) {
            MaterialProgress::query()->create([
                'candidate_profile_id' => $application->candidate_profile_id,
                'learning_material_id' => $material->id,
                'first_opened_at' => now(),
                'completed_at' => now(),
            ]);
        }

        $this->actingAs($this->hr)->get(route('admin.reports.index', ['report' => 'learning_progress', 'program' => $program->public_id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.meta.total', 2)
                ->where('rows.data.0.name', 'Setengah Jalan')
                ->where('rows.data.0.percent', '50.00')
                ->where('rows.data.0.status', 'Sedang berjalan')
                ->where('rows.data.1.status', 'Selesai'));

        $this->get(route('admin.reports.index', ['report' => 'incomplete_materials']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.meta.total', 1)
                ->where('rows.data.0.name', 'Setengah Jalan')
                ->where('rows.data.0.remaining', 1));

        $this->get(route('admin.reports.index', ['report' => 'candidates']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.0.name', 'Setengah Jalan')
                ->where('rows.data.0.learning_percent', '50.00'));
    }

    public function test_filters_outside_the_scope_are_rejected()
    {
        $this->actingAs($this->hr)
            ->get(route('admin.reports.index', ['report' => 'candidates', 'campaign' => RecruitmentCampaign::factory()->create()->public_id]))
            ->assertSessionHasErrors('campaign');
        $this->get(route('admin.reports.index', ['report' => 'tidak_ada']))->assertSessionHasErrors('report');
    }

    public function test_exports_are_generated_on_the_queue_and_only_the_requester_can_download()
    {
        $this->applicant('Rina', 75);

        $this->actingAs($this->hr)
            ->post(route('admin.exports.store'), ['report' => 'exam_results', 'exam' => $this->exam->public_id, 'program' => LearningProgram::factory()->create()->public_id])
            ->assertRedirect(route('admin.exports.index'));

        $export = ReportExport::query()->sole();
        $this->assertSame(ExportStatus::Ready, $export->status);
        $this->assertSame(['exam' => $this->exam->public_id], $export->filters, 'Filter yang tidak relevan tidak disimpan.');
        $this->assertTrue($export->expires_at->greaterThan(now()->addDays(6)));
        $this->assertTrue(ActivityLog::query()->where('action', 'export.created')->exists());

        $csv = Storage::disk(GenerateReportExport::DISK)->get($export->file_path);
        // BOM UTF-8, lalu judul kolom; kolom berisi spasi diberi tanda kutip (RFC 4180).
        $this->assertStringStartsWith("\xEF\xBB\xBF\"Nomor kandidat\",Nama,Rekrutmen,Ujian", $csv);
        $this->assertStringContainsString('Rina', $csv);
        $this->assertStringContainsString('75.00', $csv);

        $this->get(route('admin.exports.index'))->assertInertia(fn (Assert $page) => $page
            ->where('exports.data.0.status', 'ready')
            ->where('exports.data.0.id', $export->public_id));

        $download = $this->get(route('admin.exports.download', $export))->assertOk();
        $this->assertStringContainsString('attachment; filename=laporan-exam-results-', $download->headers->get('Content-Disposition'));
        $this->assertTrue(ActivityLog::query()->where('action', 'export.downloaded')->exists());

        // Pengguna lain, termasuk Super Admin, tidak dapat mengunduh.
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.exports.download', $export))->assertNotFound();
        $this->actingAs(User::factory()->hr()->create())->get(route('admin.exports.index'))
            ->assertInertia(fn (Assert $page) => $page->where('exports.data', []));
    }

    public function test_exports_follow_the_requester_scope()
    {
        $this->applicant('Dalam Lingkup', 70);
        $this->applicant('Luar Lingkup', 90, RecruitmentCampaign::factory()->create());

        $this->actingAs($this->hr)->post(route('admin.exports.store'), ['report' => 'candidates']);

        $csv = Storage::disk(GenerateReportExport::DISK)->get(ReportExport::query()->sole()->file_path);
        $this->assertStringContainsString('Dalam Lingkup', $csv);
        $this->assertStringNotContainsString('Luar Lingkup', $csv);
    }

    public function test_spreadsheet_formulas_are_neutralised_in_csv_cells()
    {
        $this->applicant('=HYPERLINK("http://contoh.test","klik")', 60);

        $this->actingAs($this->hr)->post(route('admin.exports.store'), ['report' => 'exam_results']);

        $csv = Storage::disk(GenerateReportExport::DISK)->get(ReportExport::query()->sole()->file_path);
        $this->assertStringContainsString("\"'=HYPERLINK(", $csv);
        $this->assertSame("'@SUM(A1)", CsvWriter::safe('@SUM(A1)'));
        $this->assertSame('85.50', CsvWriter::safe('85.50'));
        $this->assertSame('', CsvWriter::safe(null));
    }

    public function test_expired_exports_cannot_be_downloaded_and_are_pruned()
    {
        $this->actingAs($this->hr)->post(route('admin.exports.store'), ['report' => 'pass_rate']);
        $export = ReportExport::query()->sole();

        $this->travel(8)->days();

        $this->get(route('admin.exports.download', $export))->assertRedirect(route('admin.exports.index'));
        $this->get(route('admin.exports.index'))->assertInertia(fn (Assert $page) => $page->where('exports.data.0.status', 'expired'));

        $this->artisan('exports:prune')->assertSuccessful();
        $this->assertSame(ExportStatus::Expired, $export->fresh()->status);
        Storage::disk(GenerateReportExport::DISK)->assertMissing($export->file_path);
    }

    public function test_only_super_admins_can_read_the_audit_log()
    {
        $this->actingAs($this->hr)->post(route('admin.exports.store'), ['report' => 'pass_rate']);
        $this->get(route('admin.audit-log.index'))->assertNotFound();

        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.audit-log.index', ['action' => 'export.created']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/audit-log/index')
                ->where('logs.meta.total', 1)
                ->where('logs.data.0.action', 'export.created')
                ->where('logs.data.0.actor.name', $this->hr->name)
                ->where('logs.data.0.subject', 'Ekspor laporan'));

        $this->assertResponseHasNoInternalIds($response);
    }

    public function test_candidates_cannot_open_reports()
    {
        $this->actingAs(CandidateProfile::factory()->activated()->create()->user);

        $this->get(route('admin.reports.index'))->assertNotFound();
        $this->post(route('admin.exports.store'), ['report' => 'candidates'])->assertNotFound();
    }
}

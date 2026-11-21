<?php

namespace Database\Seeders\Demo;

use App\Domain\Candidates\Enums\CampaignStatus;
use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\CampaignStage;
use App\Domain\Candidates\Models\CandidateEvaluation;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\Department;
use App\Domain\Candidates\Models\InternalNote;
use App\Domain\Candidates\Models\Position;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Exams\Enums\ExamType;
use App\Domain\Exams\Enums\ResultRelease;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\LearningModule;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Models\MaterialProgress;
use App\Domain\Learning\Models\ProgramAssignment;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\QuestionBank\Models\QuestionCategory;
use App\Domain\Shared\Enums\AssignmentSource;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Data contoh untuk pengembangan. Tidak pernah dijalankan di produksi.
 *
 * Semua akun demo memakai password "password".
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    /**
     * Urutan langkah normal untuk membuat riwayat tahap yang masuk akal.
     * `p1` dan `p2` adalah tahap proses pertama dan kedua di alur rekrutmen.
     */
    private const STAGE_PATH = ['invited', 'registered', 'p1', 'p2', 'hired'];

    private int $candidateSequence = 0;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder tidak boleh dijalankan di produksi.');
        }

        [$hrSales, $hrService] = $this->seedHrUsers();
        $positions = $this->seedPositions();
        $campaigns = $this->seedCampaigns($positions, $hrSales, $hrService);
        $applications = $this->seedCandidates($campaigns, $hrSales);
        $programs = $this->seedPrograms($positions);
        $this->assignPrograms($programs, $applications, $hrSales, $hrService);
        $exams = $this->seedQuestionBankAndExams($programs);
        $this->assignExams($exams, $applications, $hrSales, $hrService);
        $this->seedEvaluations($applications['sales'], $hrSales);

        $this->command?->info('Data demo dibuat. Semua akun demo memakai password "'.self::PASSWORD.'".');
    }

    /**
     * @return array{User, User}
     */
    private function seedHrUsers(): array
    {
        return [
            User::factory()->hr()->create(['name' => 'HR Sales Demo', 'email' => 'hr.sales@demo.test']),
            User::factory()->hr()->create(['name' => 'HR Layanan Demo', 'email' => 'hr.layanan@demo.test']),
        ];
    }

    /**
     * @return array<string, Position>
     */
    private function seedPositions(): array
    {
        $sales = Department::factory()->create(['name' => 'Sales']);
        $service = Department::factory()->create(['name' => 'Customer Service']);
        $engineering = Department::factory()->create(['name' => 'Engineering']);

        return [
            'sales' => Position::factory()->for($sales)->create(['name' => 'Sales Executive']),
            'service' => Position::factory()->for($service)->create(['name' => 'Customer Service Officer']),
            'backend' => Position::factory()->for($engineering)->create(['name' => 'Backend Developer']),
        ];
    }

    /**
     * @param  array<string, Position>  $positions
     * @return array<string, RecruitmentCampaign>
     */
    private function seedCampaigns(array $positions, User $hrSales, User $hrService): array
    {
        $campaign = fn (string $name, Position $position, CampaignStatus $status, string $startsOn) => RecruitmentCampaign::factory()
            ->for($position)
            ->create([
                'name' => $name,
                'status' => $status,
                'starts_on' => $startsOn,
                'ends_on' => now()->parse($startsOn)->addMonths(2)->toDateString(),
                'closed_at' => $status === CampaignStatus::Closed ? now()->subWeek() : null,
                'created_by' => $hrSales->id,
            ]);

        $campaigns = [
            'sales' => $campaign('Sales Executive — Oktober 2026', $positions['sales'], CampaignStatus::Open, '2026-10-01'),
            'service' => $campaign('Customer Service — Oktober 2026', $positions['service'], CampaignStatus::Open, '2026-10-05'),
            'backend' => $campaign('Backend Developer — Juli 2026', $positions['backend'], CampaignStatus::Closed, '2026-07-01'),
            'salesDraft' => $campaign('Sales Executive — Desember 2026', $positions['sales'], CampaignStatus::Draft, '2026-12-01'),
        ];

        $campaigns['sales']->hrUsers()->attach($hrSales->id);
        $campaigns['backend']->hrUsers()->attach($hrSales->id);
        $campaigns['salesDraft']->hrUsers()->attach($hrSales->id);
        $campaigns['service']->hrUsers()->attach($hrService->id);

        // Contoh alur yang disesuaikan: rekrutmen Backend punya tahap tambahan.
        $campaigns['backend']->stages()->create(['name' => 'Tes coding', 'position' => 3]);

        // Contoh jabatan lain: Head HR melihat semua rekrutmen tanpa ditugaskan;
        // Interviewer hanya melihat rekrutmen Sales dan mengisi evaluasi.
        User::factory()->headHr()->create(['name' => 'Head HR Demo', 'email' => 'head.hr@demo.test']);
        $interviewer = User::factory()->interviewer()->create(['name' => 'Interviewer Demo', 'email' => 'interviewer@demo.test']);
        $campaigns['sales']->hrUsers()->attach($interviewer->id);

        return $campaigns;
    }

    /**
     * @param  array<string, RecruitmentCampaign>  $campaigns
     * @return array<string, Collection<int, RecruitmentApplication>>
     */
    private function seedCandidates(array $campaigns, User $hrSales): array
    {
        $sales = collect([
            $this->createApplication($campaigns['sales'], 'p1', $hrSales, [
                'name' => 'Kandidat Demo',
                'email' => 'kandidat@demo.test',
            ]),
            $this->createApplication($campaigns['sales'], 'registered', $hrSales),
            $this->createApplication($campaigns['sales'], 'registered', $hrSales),
            $this->createApplication($campaigns['sales'], 'p1', $hrSales),
            $this->createApplication($campaigns['sales'], 'p2', $hrSales),
            $this->createApplication($campaigns['sales'], 'rejected', $hrSales),
            $this->createApplication($campaigns['sales'], 'invited', $hrSales),
            $this->createApplication($campaigns['sales'], 'invited', $hrSales),
        ]);

        $service = collect(['registered', 'registered', 'p1', 'p2', 'withdrawn', 'invited'])
            ->map(fn (string $step) => $this->createApplication($campaigns['service'], $step, $hrSales));

        $backend = collect(['hired', 'rejected', 'rejected'])
            ->map(fn (string $step) => $this->createApplication($campaigns['backend'], $step, $hrSales));

        // Satu kandidat melamar di dua rekrutmen: membuktikan lamaran terpisah dari profil.
        $repeatCandidate = $backend->get(1)->candidateProfile;
        $sales->push($this->createApplication($campaigns['sales'], 'registered', $hrSales, profile: $repeatCandidate));

        DB::table('candidate_number_sequences')->updateOrInsert(
            ['year' => now()->year],
            ['last_number' => $this->candidateSequence],
        );

        foreach ($sales->take(5) as $application) {
            InternalNote::factory()->for($application, 'application')->create([
                'author_id' => $hrSales->id,
                'body' => 'Catatan wawancara awal: komunikasi jelas, perlu digali pengalaman lapangan.',
            ]);
        }

        return ['sales' => $sales, 'service' => $service, 'backend' => $backend];
    }

    /**
     * @param  array{name?: string, email?: string}  $userAttributes
     */
    /**
     * @param  string  $step  Salah satu STAGE_PATH, atau 'rejected' / 'withdrawn'.
     */
    private function createApplication(
        RecruitmentCampaign $campaign,
        string $step,
        User $actor,
        array $userAttributes = [],
        ?CandidateProfile $profile = null,
    ): RecruitmentApplication {
        $activated = $step !== 'invited';

        // Kandidat lama yang sudah aktif langsung "registered" saat ditambahkan ke rekrutmen baru.
        $initialStep = $profile?->activated_at !== null ? 'registered' : 'invited';

        if ($profile === null) {
            $user = User::factory()->candidate()->create($userAttributes);
            $profileFactory = CandidateProfile::factory()->for($user);

            if ($activated) {
                $profileFactory = $profileFactory->activated();
            }

            $profile = $profileFactory->create([
                'candidate_number' => sprintf('CAND-%d-%06d', now()->year, ++$this->candidateSequence),
                'registered_on' => $campaign->starts_on,
            ]);
        }

        $path = $this->pathTo($initialStep, $step);
        // Tahap proses terakhir yang dilalui (juga untuk kandidat yang tidak lolos atau mengundurkan diri).
        $lastProcess = collect($path)->last(fn (string $item) => str_starts_with($item, 'p'));

        $application = RecruitmentApplication::factory()
            ->for($profile)
            ->for($campaign, 'campaign')
            ->create([
                'stage' => $this->stageOf($step),
                'campaign_stage_id' => $lastProcess === null ? null : $this->processStage($campaign, $lastProcess)->id,
                'applied_at' => $campaign->starts_on,
            ]);

        $this->recordStageHistory($application, $path, $actor);

        return $application;
    }

    /**
     * @return array<int, string>
     */
    private function pathTo(string $initialStep, string $finalStep): array
    {
        $path = array_slice(self::STAGE_PATH, (int) array_search($initialStep, self::STAGE_PATH, true));

        if (in_array($finalStep, ['rejected', 'withdrawn'], true)) {
            // Contoh: tidak lolos atau mengundurkan diri setelah tahap proses pertama.
            return [...array_slice($path, 0, (int) array_search('p1', $path, true) + 1), $finalStep];
        }

        return array_slice($path, 0, (int) array_search($finalStep, $path, true) + 1);
    }

    private function stageOf(string $step): RecruitmentStage
    {
        return str_starts_with($step, 'p') ? RecruitmentStage::InProcess : RecruitmentStage::from($step);
    }

    private function processStage(RecruitmentCampaign $campaign, string $step): CampaignStage
    {
        return $campaign->stages()->where('position', (int) substr($step, 1))->firstOrFail();
    }

    private function labelOf(RecruitmentCampaign $campaign, string $step): string
    {
        return str_starts_with($step, 'p') ? $this->processStage($campaign, $step)->name : RecruitmentStage::from($step)->label();
    }

    /**
     * @param  array<int, string>  $path
     */
    private function recordStageHistory(RecruitmentApplication $application, array $path, User $actor): void
    {
        $campaign = $application->campaign;
        $from = null;

        foreach ($path as $to) {
            $application->stageHistories()->create([
                'from_stage' => $from === null ? null : $this->stageOf($from),
                'to_stage' => $this->stageOf($to),
                'from_label' => $from === null ? null : $this->labelOf($campaign, $from),
                'to_label' => $this->labelOf($campaign, $to),
                'reason' => match ($to) {
                    'rejected' => 'Belum memenuhi kualifikasi pengalaman.',
                    'withdrawn' => 'Kandidat mengundurkan diri.',
                    default => null,
                },
                // Aktivasi akun (invited → registered) dilakukan kandidat sendiri, bukan HR.
                'changed_by' => $from === 'invited' && $to === 'registered' ? null : $actor->id,
            ]);

            $from = $to;
        }
    }

    /**
     * @param  array<string, Position>  $positions
     * @return array<string, LearningProgram>
     */
    private function seedPrograms(array $positions): array
    {
        $sales = LearningProgram::factory()->published()->create([
            'name' => 'Onboarding Sales Executive',
            'description' => 'Pengenalan produk, proses penjualan, dan etika komunikasi dengan pelanggan.',
            'category' => 'Onboarding',
            'target_position_id' => $positions['sales']->id,
            'estimated_minutes' => 120,
        ]);

        $this->createModule($sales, 1, 'Mengenal Produk', [
            ['type' => 'article', 'title' => 'Katalog produk utama'],
            ['type' => 'video', 'title' => 'Video demo produk'],
            ['type' => 'link', 'title' => 'Lembar spesifikasi lengkap', 'required' => false],
        ]);
        $this->createModule($sales, 2, 'Proses Penjualan', [
            ['type' => 'article', 'title' => 'Tahapan penjualan B2B'],
            ['type' => 'article', 'title' => 'Menangani keberatan pelanggan'],
        ]);

        $service = LearningProgram::factory()->published()->create([
            'name' => 'Dasar Pelayanan Pelanggan',
            'description' => 'Standar layanan, SOP eskalasi, dan komunikasi empatik.',
            'category' => 'SOP',
            'target_position_id' => $positions['service']->id,
            'estimated_minutes' => 90,
        ]);

        $this->createModule($service, 1, 'Standar Layanan', [
            ['type' => 'article', 'title' => 'Prinsip layanan prima'],
            ['type' => 'article', 'title' => 'SOP eskalasi keluhan'],
            ['type' => 'video', 'title' => 'Contoh percakapan yang baik', 'required' => false],
        ]);

        LearningProgram::factory()->create([
            'name' => 'Pengenalan Budaya Perusahaan',
            'category' => 'Onboarding',
        ]);

        return ['sales' => $sales, 'service' => $service];
    }

    /**
     * @param  list<array{type: string, title: string, required?: bool}>  $materials
     */
    private function createModule(LearningProgram $program, int $position, string $title, array $materials): void
    {
        $module = LearningModule::factory()->for($program, 'program')->create([
            'title' => $title,
            'position' => $position,
        ]);

        foreach ($materials as $index => $material) {
            $factory = LearningMaterial::factory()->for($module, 'module');

            $factory = match ($material['type']) {
                'video' => $factory->videoEmbed(),
                'link' => $factory->link(),
                default => $factory,
            };

            $factory->create([
                'title' => $material['title'],
                'position' => $index + 1,
                'is_required' => $material['required'] ?? true,
            ]);
        }
    }

    /**
     * @param  array<string, LearningProgram>  $programs
     * @param  array<string, Collection<int, RecruitmentApplication>>  $applications
     */
    private function assignPrograms(array $programs, array $applications, User $hrSales, User $hrService): void
    {
        $assign = function (LearningProgram $program, Collection $targets, User $assigner): void {
            foreach ($targets as $application) {
                ProgramAssignment::factory()->create([
                    'learning_program_id' => $program->id,
                    'recruitment_application_id' => $application->id,
                    'assigned_via' => AssignmentSource::Campaign,
                    'assigned_by' => $assigner->id,
                    'due_at' => now()->addWeeks(2),
                ]);
            }
        };

        $assign($programs['sales'], $this->activeApplications($applications['sales']), $hrSales);
        $assign($programs['service'], $this->activeApplications($applications['service']), $hrService);

        // Kandidat demo sudah menyelesaikan sebagian materi wajib.
        $demoCandidate = $applications['sales']->first()->candidateProfile;
        $salesMaterials = $programs['sales']->materials()->orderBy('learning_modules.position')->orderBy('learning_materials.position')->get();

        foreach ($salesMaterials->take(3) as $material) {
            MaterialProgress::factory()->completed()->create([
                'candidate_profile_id' => $demoCandidate->id,
                'learning_material_id' => $material->id,
            ]);
        }

        MaterialProgress::factory()->create([
            'candidate_profile_id' => $demoCandidate->id,
            'learning_material_id' => $salesMaterials->get(3)->id,
        ]);
    }

    /**
     * @param  array<string, LearningProgram>  $programs
     * @return array<string, Exam>
     */
    private function seedQuestionBankAndExams(array $programs): array
    {
        $product = QuestionCategory::factory()->create(['name' => 'Pengetahuan Produk']);
        $communication = QuestionCategory::factory()->create(['name' => 'Komunikasi']);
        $logic = QuestionCategory::factory()->create(['name' => 'Logika']);
        $sop = QuestionCategory::factory()->create(['name' => 'SOP Layanan']);

        $salesQuestions = collect([
            ...Question::factory()->count(4)->multipleChoice()->for($product, 'category')->create(),
            Question::factory()->multipleSelect()->for($product, 'category')->create(),
            Question::factory()->trueFalse()->for($communication, 'category')->create(),
            Question::factory()->shortAnswer()->for($logic, 'category')->create(),
            Question::factory()->essay()->for($communication, 'category')->create(['default_points' => 20]),
        ]);

        $serviceQuestions = collect([
            ...Question::factory()->count(3)->multipleChoice()->for($sop, 'category')->create(),
            Question::factory()->trueFalse()->for($sop, 'category')->create(),
            Question::factory()->caseStudy()->for($communication, 'category')->create([
                'body_html' => '<p>Seorang pelanggan menghubungi layanan untuk ketiga kalinya karena pesanan belum diterima.</p>',
            ]),
        ]);

        // Soal tambahan di bank soal yang belum dipakai ujian mana pun.
        Question::factory()->count(3)->multipleChoice()->for($logic, 'category')->create();
        Question::factory()->archived()->for($product, 'category')->create();

        $salesExam = Exam::factory()->published()->withQuestions($salesQuestions)->create([
            'title' => 'Tes Pengetahuan Produk Sales',
            'type' => ExamType::ProductKnowledge,
            'duration_minutes' => 30,
            'max_attempts' => 2,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'show_review' => true,
        ]);
        $salesExam->prerequisitePrograms()->attach($programs['sales']->id);
        $programs['sales']->exams()->attach($salesExam->id);

        $serviceExam = Exam::factory()->published()->withQuestions($serviceQuestions)->create([
            'title' => 'Studi Kasus Pelayanan Pelanggan',
            'type' => ExamType::CaseStudy,
            'duration_minutes' => 45,
            'result_release' => ResultRelease::Manual,
        ]);
        $programs['service']->exams()->attach($serviceExam->id);

        Exam::factory()->create([
            'title' => 'Pre-test Sales (draft)',
            'type' => ExamType::PreTest,
        ]);

        return ['sales' => $salesExam, 'service' => $serviceExam];
    }

    /**
     * @param  array<string, Exam>  $exams
     * @param  array<string, Collection<int, RecruitmentApplication>>  $applications
     */
    private function assignExams(array $exams, array $applications, User $hrSales, User $hrService): void
    {
        foreach ($this->activeApplications($applications['sales']) as $index => $application) {
            ExamAssignment::factory()->create([
                'exam_id' => $exams['sales']->id,
                'recruitment_application_id' => $application->id,
                'assigned_via' => AssignmentSource::Campaign,
                'assigned_by' => $hrSales->id,
                // Contoh jadwal khusus: satu kandidat mendapat perpanjangan.
                'closes_at_override' => $index === 1 ? $exams['sales']->closes_at?->addWeek() : null,
            ]);
        }

        foreach ($this->activeApplications($applications['service']) as $application) {
            ExamAssignment::factory()->create([
                'exam_id' => $exams['service']->id,
                'recruitment_application_id' => $application->id,
                'assigned_via' => AssignmentSource::Campaign,
                'assigned_by' => $hrService->id,
            ]);
        }
    }

    /**
     * @param  Collection<int, RecruitmentApplication>  $applications
     */
    private function seedEvaluations(Collection $applications, User $hrSales): void
    {
        // Kandidat di tahap proses kedua (Lolos seleksi awal) sudah dievaluasi.
        $shortlisted = $applications->first(fn (RecruitmentApplication $application) => $application->stage === RecruitmentStage::InProcess
            && $application->campaignStage?->position === 2);

        if ($shortlisted !== null) {
            CandidateEvaluation::factory()->finalized()->create([
                'recruitment_application_id' => $shortlisted->id,
                'evaluator_id' => $hrSales->id,
            ]);
        }
    }

    /**
     * Lamaran yang masih berjalan dan akunnya sudah aktif.
     *
     * @param  Collection<int, RecruitmentApplication>  $applications
     * @return Collection<int, RecruitmentApplication>
     */
    private function activeApplications(Collection $applications): Collection
    {
        return $applications
            ->filter(fn (RecruitmentApplication $application) => in_array($application->stage, [
                RecruitmentStage::Registered,
                RecruitmentStage::InProcess,
            ], true))
            ->values();
    }
}

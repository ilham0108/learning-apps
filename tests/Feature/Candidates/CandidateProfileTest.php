<?php

namespace Tests\Feature\Candidates;

use App\Domain\Candidates\Enums\CandidateDocumentType;
use App\Domain\Candidates\Models\CandidateDocument;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\Shared\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsNoInternalIds;
use Tests\TestCase;

class CandidateProfileTest extends TestCase
{
    use AssertsNoInternalIds, RefreshDatabase;

    private CandidateProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(CandidateDocument::DISK);
        $this->profile = CandidateProfile::factory()->activated()->create();
        $this->actingAs($this->profile->user);
    }

    /**
     * @return array<string, string>
     */
    private function personal(array $overrides = []): array
    {
        return [
            'name' => 'Rina Kartika',
            'phone' => '081234567890',
            'city' => 'Bandung',
            'birth_date' => '1998-05-12',
            'religion' => 'islam',
            'marital_status' => 'belum_menikah',
            'linkedin_url' => 'https://www.linkedin.com/in/rina',
            'portfolio_url' => '',
            ...$overrides,
        ];
    }

    private function upload(string $type, UploadedFile $file, ?string $label = null): TestResponse
    {
        return $this->post(route('candidate-profile.documents.store'), array_filter([
            'type' => $type,
            'file' => $file,
            'label' => $label,
        ]));
    }

    private function completeProfile(): void
    {
        $this->put(route('candidate-profile.personal.update'), $this->personal())->assertSessionHasNoErrors();
        $this->post(route('candidate-profile.educations.store'), ['level' => 's1', 'institution' => 'Universitas Padjadjaran', 'graduation_year' => 2020])
            ->assertSessionHasNoErrors();
        $this->upload('cv', UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf'))->assertSessionHasNoErrors();
        $this->upload('ktp', UploadedFile::fake()->image('ktp.jpg'))->assertSessionHasNoErrors();
        $this->upload('photo', UploadedFile::fake()->image('foto.png'))->assertSessionHasNoErrors();
    }

    public function test_new_profiles_start_incomplete_with_a_checklist()
    {
        $response = $this->get(route('candidate-profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/profile/edit')
                ->where('completeness.complete', false)
                ->where('completeness.percent', 0)
                ->has('completeness.items', 5)
                ->where('personal.email', $this->profile->user->email));

        $this->assertResponseHasNoInternalIds($response);
    }

    public function test_completing_every_required_part_marks_the_profile_complete()
    {
        $this->completeProfile();

        $this->get(route('candidate-profile.edit'))->assertInertia(fn (Assert $page) => $page
            ->where('completeness.complete', true)
            ->where('completeness.percent', 100)
            ->where('personal.religion', 'islam')
            ->has('documents', 3));

        $this->profile->refresh();
        $this->assertSame('Rina Kartika', $this->profile->user->name);
        $this->assertSame('Bandung', $this->profile->city);
    }

    public function test_personal_data_is_validated()
    {
        $this->put(route('candidate-profile.personal.update'), $this->personal([
            'birth_date' => now()->subYears(10)->toDateString(),
            'religion' => 'lainnya',
            'phone' => 'abc',
            'linkedin_url' => 'bukan-url',
        ]))->assertSessionHasErrors(['birth_date', 'religion', 'phone', 'linkedin_url']);

        // Email tidak dapat diubah kandidat dari halaman ini.
        $email = $this->profile->user->email;
        $this->put(route('candidate-profile.personal.update'), $this->personal(['email' => 'baru@contoh.com']));
        $this->assertSame($email, $this->profile->user->fresh()->email);
    }

    public function test_educations_and_experiences_can_be_managed_only_by_their_owner()
    {
        $this->post(route('candidate-profile.educations.store'), ['level' => 's1', 'institution' => 'ITB', 'start_year' => 2018, 'graduation_year' => 2016])
            ->assertSessionHasErrors('graduation_year');
        $this->post(route('candidate-profile.educations.store'), ['level' => 'd3', 'institution' => 'Politeknik Negeri Bandung'])
            ->assertSessionHasNoErrors();
        $education = $this->profile->educations()->sole();

        $this->post(route('candidate-profile.experiences.store'), [
            'company' => 'PT Maju', 'job_title' => 'Sales', 'started_on' => '2023-05', 'ended_on' => '2022-01',
        ])->assertSessionHasErrors('ended_on');
        $this->post(route('candidate-profile.experiences.store'), [
            'company' => 'PT Maju', 'job_title' => 'Sales', 'started_on' => '2021-03',
        ])->assertSessionHasNoErrors();
        $experience = $this->profile->experiences()->sole();
        $this->assertSame('2021-03-01', $experience->started_on->toDateString());
        $this->assertNull($experience->ended_on);

        $this->actingAs(CandidateProfile::factory()->activated()->create()->user);
        $this->put(route('candidate-profile.educations.update', $education), ['level' => 's1', 'institution' => 'Diubah'])->assertNotFound();
        $this->delete(route('candidate-profile.experiences.destroy', $experience))->assertNotFound();
        $this->assertSame('Politeknik Negeri Bandung', $education->fresh()->institution);
        $this->assertNotNull($experience->fresh());
    }

    public function test_documents_are_validated_by_type_and_size()
    {
        $this->upload('cv', UploadedFile::fake()->image('cv.jpg'))->assertSessionHasErrors('file');
        $this->upload('cv', UploadedFile::fake()->create('cv.pdf', 6000, 'application/pdf'))->assertSessionHasErrors('file');
        $this->upload('photo', UploadedFile::fake()->create('foto.php', 10, 'application/x-php'))->assertSessionHasErrors('file');
        $this->upload('certificate', UploadedFile::fake()->create('s.pdf', 10, 'application/pdf'))->assertSessionHasErrors('label');

        $this->assertSame(0, CandidateDocument::query()->count());
    }

    public function test_uploading_a_new_cv_replaces_the_old_file()
    {
        $this->upload('cv', UploadedFile::fake()->create('cv-lama.pdf', 100, 'application/pdf'))->assertSessionHasNoErrors();
        $old = $this->profile->documents()->sole();

        $this->upload('cv', UploadedFile::fake()->create('cv-baru.pdf', 100, 'application/pdf'))->assertSessionHasNoErrors();
        $new = $this->profile->documents()->sole();

        $this->assertSame('cv-baru.pdf', $new->file_original_name);
        Storage::disk(CandidateDocument::DISK)->assertMissing($old->file_path);
        Storage::disk(CandidateDocument::DISK)->assertExists($new->file_path);
        $this->assertStringNotContainsString('cv-baru', $new->file_path, 'Nama file disimpan acak.');
    }

    public function test_certificates_are_limited_to_five()
    {
        foreach (range(1, CandidateDocumentType::MAX_CERTIFICATES) as $number) {
            $this->upload('certificate', UploadedFile::fake()->create("s{$number}.pdf", 10, 'application/pdf'), "Sertifikat {$number}")
                ->assertSessionHasNoErrors();
        }

        $this->upload('certificate', UploadedFile::fake()->create('s6.pdf', 10, 'application/pdf'), 'Sertifikat 6')
            ->assertSessionHasErrors('file');
        $this->assertSame(5, $this->profile->documents()->count());
    }

    public function test_document_files_need_a_signed_url_and_access_rights()
    {
        $this->upload('ktp', UploadedFile::fake()->image('ktp.jpg'))->assertSessionHasNoErrors();
        $document = $this->profile->documents()->sole();
        $signed = URL::temporarySignedRoute('candidate-documents.file', now()->addMinutes(10), $document);

        $this->get($signed)->assertOk();
        $this->get(route('candidate-documents.file', $document))->assertForbidden();
        $this->assertSame(0, ActivityLog::query()->where('action', 'candidate_document.viewed')->count(), 'Pemilik membuka sendiri: tidak dicatat.');

        // Kandidat lain dan HR di luar lingkup tidak dapat membuka.
        $this->actingAs(CandidateProfile::factory()->activated()->create()->user)->get($signed)->assertNotFound();
        $this->actingAs(User::factory()->hr()->create())->get($signed)->assertNotFound();

        // HR yang menangani lamaran kandidat dapat membuka, dan pembukaan KTP dicatat.
        $hr = User::factory()->hr()->create();
        RecruitmentApplication::factory()->for($this->profile)
            ->for(RecruitmentCampaign::factory()->assignedTo($hr), 'campaign')->create();
        $this->actingAs($hr)->get($signed)->assertOk();
        $this->assertSame(1, ActivityLog::query()->where('action', 'candidate_document.viewed')->count());

        // Hanya kandidat pemilik yang dapat menghapus.
        $this->delete(route('candidate-profile.documents.destroy', $document))->assertNotFound();
        $this->actingAs($this->profile->user)->delete(route('candidate-profile.documents.destroy', $document))->assertRedirect();
        Storage::disk(CandidateDocument::DISK)->assertMissing($document->file_path);
    }

    public function test_exams_cannot_start_until_the_profile_is_complete()
    {
        $application = RecruitmentApplication::factory()->for($this->profile)->create();
        $exam = Exam::factory()->published()->withQuestions([Question::factory()->create()])->create();
        $assignment = ExamAssignment::factory()->create(['exam_id' => $exam->id, 'recruitment_application_id' => $application->id]);

        $this->get(route('exams.show', $assignment))->assertInertia(fn (Assert $page) => $page
            ->where('assignment.availability', 'profile_incomplete'));
        $this->post(route('exams.start', $assignment))->assertRedirect(route('exams.show', $assignment));
        $this->assertSame(0, $assignment->attempts()->count());

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('profileCompleteness.complete', false));

        $this->completeProfile();

        $this->post(route('exams.start', $assignment))->assertRedirectContains('/attempts/');
        $this->assertSame(1, $assignment->attempts()->count());
    }

    public function test_hr_sees_personal_data_on_the_candidate_page()
    {
        $this->completeProfile();
        $hr = User::factory()->hr()->create();
        RecruitmentApplication::factory()->for($this->profile)
            ->for(RecruitmentCampaign::factory()->assignedTo($hr), 'campaign')->create();

        $response = $this->actingAs($hr)->get(route('admin.candidates.show', $this->profile))
            ->assertInertia(fn (Assert $page) => $page
                ->where('personalData.personal.city', 'Bandung')
                ->where('personalData.completeness.complete', true)
                ->has('personalData.educations', 1)
                ->has('personalData.documents', 3));

        $this->assertResponseHasNoInternalIds($response);
    }

    public function test_staff_cannot_open_the_candidate_profile_page()
    {
        $this->actingAs(User::factory()->hr()->create())->get(route('candidate-profile.edit'))->assertNotFound();
    }
}

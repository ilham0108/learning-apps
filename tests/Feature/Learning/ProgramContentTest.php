<?php

namespace Tests\Feature\Learning;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Learning\Enums\MaterialType;
use App\Domain\Learning\Enums\ProgramStatus;
use App\Domain\Learning\Enums\VideoProvider;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\LearningModule;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Models\MaterialProgress;
use App\Domain\Learning\Support\MaterialContent;
use App\Domain\Shared\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgramContentTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private LearningModule $module;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->hr = User::factory()->hr()->create();
        $this->module = LearningModule::factory()->create(['position' => 1]);
        $this->actingAs($this->hr);
    }

    /**
     * @return array<string, mixed>
     */
    private function material(array $overrides = []): array
    {
        return ['title' => 'Materi', 'type' => 'article', 'is_required' => true, 'body_html' => '<p>Isi</p>', ...$overrides];
    }

    public function test_hr_can_create_a_program_and_add_modules()
    {
        $this->post(route('admin.programs.store'), [
            'name' => 'Onboarding Gudang',
            'status' => 'draft',
            'estimated_minutes' => 90,
        ])->assertSessionHasNoErrors();

        $program = LearningProgram::query()->where('name', 'Onboarding Gudang')->sole();
        $this->assertSame($this->hr->id, $program->created_by);

        $this->post(route('admin.modules.store', $program), ['title' => 'Modul 1'])->assertSessionHasNoErrors();
        $this->post(route('admin.modules.store', $program), ['title' => 'Modul 2'])->assertSessionHasNoErrors();

        $this->assertSame([1, 2], $program->modules()->pluck('position')->all());
    }

    public function test_article_html_is_sanitized()
    {
        $this->post(route('admin.materials.store', $this->module), $this->material([
            'body_html' => '<p onclick="steal()">Halo <strong>dunia</strong></p><script>alert(1)</script><a href="javascript:alert(1)">x</a><iframe src="https://evil.test"></iframe>',
        ]))->assertSessionHasNoErrors();

        $html = LearningMaterial::query()->sole()->body_html;
        $this->assertStringContainsString('<strong>dunia</strong>', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('iframe', $html);
    }

    public function test_an_article_with_only_markup_is_rejected()
    {
        $this->post(route('admin.materials.store', $this->module), $this->material(['body_html' => '<p><script>x</script></p>']))
            ->assertSessionHasErrors('body_html');
    }

    public function test_video_embeds_must_be_youtube_or_vimeo()
    {
        $this->post(route('admin.materials.store', $this->module), $this->material([
            'type' => 'video_embed', 'body_html' => null, 'external_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(VideoProvider::Youtube, LearningMaterial::query()->sole()->video_provider);

        $this->post(route('admin.materials.store', $this->module), $this->material([
            'type' => 'video_embed', 'body_html' => null, 'external_url' => 'https://evil.test/video.mp4',
        ]))->assertSessionHasErrors('external_url');
    }

    public function test_files_are_stored_privately_with_validated_type_and_size()
    {
        $this->post(route('admin.materials.store', $this->module), $this->material([
            'type' => 'pdf', 'body_html' => null,
            'file' => UploadedFile::fake()->create('panduan.pdf', 300, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $material = LearningMaterial::query()->sole();
        $this->assertSame(MaterialType::Pdf, $material->type);
        $this->assertSame('panduan.pdf', $material->file_original_name);
        $this->assertStringStartsWith('materials/'.$this->module->program->public_id.'/', $material->file_path);
        $this->assertStringNotContainsString('panduan', $material->file_path);
        Storage::disk('local')->assertExists($material->file_path);
    }

    public function test_dangerous_or_oversized_files_are_rejected()
    {
        $cases = [
            ['pdf', UploadedFile::fake()->create('evil.php', 10, 'application/pdf')],
            ['pdf', UploadedFile::fake()->create('fake.pdf', 10, 'text/html')],
            ['pdf', UploadedFile::fake()->create('besar.pdf', 21000, 'application/pdf')],
            ['image', UploadedFile::fake()->create('foto.gif', 10, 'image/gif')],
            ['image', UploadedFile::fake()->create('foto.png', 6000, 'image/png')],
            ['video_file', UploadedFile::fake()->create('video.mov', 10, 'video/quicktime')],
        ];

        foreach ($cases as [$type, $file]) {
            $this->post(route('admin.materials.store', $this->module), $this->material([
                'type' => $type, 'body_html' => null, 'file' => $file,
            ]))->assertSessionHasErrors('file');
        }

        $this->post(route('admin.materials.store', $this->module), $this->material([
            'type' => 'image', 'body_html' => null,
        ]))->assertSessionHasErrors('file');

        $this->assertSame(0, LearningMaterial::query()->count());
    }

    public function test_replacing_a_file_removes_the_old_one_and_the_type_cannot_change()
    {
        $this->post(route('admin.materials.store', $this->module), $this->material([
            'type' => 'image', 'body_html' => null, 'file' => UploadedFile::fake()->image('a.png'),
        ]));
        $material = LearningMaterial::query()->sole();
        $oldPath = $material->file_path;

        $this->post(route('admin.materials.update', $material), $this->material([
            'type' => 'image', 'body_html' => null, 'file' => UploadedFile::fake()->image('b.jpg'),
        ]))->assertSessionHasNoErrors();

        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($material->refresh()->file_path);

        // Tanpa file baru, file lama dipertahankan.
        $this->post(route('admin.materials.update', $material), $this->material([
            'type' => 'image', 'body_html' => null, 'title' => 'Judul baru',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('Judul baru', $material->refresh()->title);

        $this->post(route('admin.materials.update', $material), $this->material())->assertSessionHasErrors('type');
    }

    public function test_materials_with_progress_are_archived_instead_of_deleted()
    {
        $used = LearningMaterial::factory()->for($this->module, 'module')->create();
        MaterialProgress::factory()->create(['learning_material_id' => $used->id]);
        $unused = LearningMaterial::factory()->for($this->module, 'module')->create();

        $this->delete(route('admin.materials.destroy', $used))->assertSessionHasNoErrors();
        $this->delete(route('admin.materials.destroy', $unused))->assertSessionHasNoErrors();

        $this->assertNotNull($used->refresh()->archived_at);
        $this->assertModelMissing($unused);
        $this->assertTrue(ActivityLog::query()->where('action', 'material.archived')->exists());

        $this->post(route('admin.materials.restore', $used))->assertSessionHasNoErrors();
        $this->assertNull($used->refresh()->archived_at);
    }

    public function test_modules_with_materials_cannot_be_deleted()
    {
        LearningMaterial::factory()->for($this->module, 'module')->create();

        $this->delete(route('admin.modules.destroy', $this->module));
        $this->assertModelExists($this->module);

        $empty = LearningModule::factory()->for($this->module->program, 'program')->create();
        $this->delete(route('admin.modules.destroy', $empty));
        $this->assertModelMissing($empty);
    }

    public function test_items_can_be_reordered()
    {
        $program = $this->module->program;
        $second = LearningModule::factory()->for($program, 'program')->create(['position' => 2]);

        $this->post(route('admin.modules.move', $second), ['direction' => 'up'])->assertSessionHasNoErrors();

        $this->assertSame([$second->id, $this->module->id], $program->modules()->pluck('id')->all());

        $this->post(route('admin.modules.move', $second), ['direction' => 'sideways'])->assertSessionHasErrors('direction');
    }

    public function test_private_files_need_a_valid_signature_and_permission()
    {
        $this->post(route('admin.materials.store', $this->module), $this->material([
            'type' => 'pdf', 'body_html' => null, 'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ]));
        $material = LearningMaterial::query()->sole();

        $this->get(route('materials.file', $material))->assertForbidden();

        $signed = MaterialContent::signedFileUrl($material);
        $this->get($signed)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        // Kandidat yang tidak ditugaskan tidak bisa membuka file walau memegang URL bertanda tangan.
        $this->actingAs(CandidateProfile::factory()->activated()->create()->user)->get($signed)->assertNotFound();

        $this->travel(11)->minutes();
        $this->actingAs($this->hr)->get($signed)->assertForbidden();
    }

    public function test_candidates_cannot_reach_admin_program_pages()
    {
        $this->actingAs(CandidateProfile::factory()->activated()->create()->user);

        $this->get(route('admin.programs.index'))->assertNotFound();
        $this->post(route('admin.programs.store'), ['name' => 'X', 'status' => ProgramStatus::Draft->value])->assertNotFound();
    }
}

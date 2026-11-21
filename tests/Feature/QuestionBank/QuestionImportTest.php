<?php

namespace Tests\Feature\QuestionBank;

use App\Domain\QuestionBank\Enums\Difficulty;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Import\QuestionSheet;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\QuestionBank\Models\QuestionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class QuestionImportTest extends TestCase
{
    use RefreshDatabase;

    private QuestionCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = QuestionCategory::factory()->create(['name' => 'Logika']);
        $this->actingAs(User::factory()->hr()->create());
    }

    /**
     * @param  list<array<string, string|int>>  $rows
     */
    private function xlsx(array $rows): UploadedFile
    {
        $columns = [...array_keys(QuestionSheet::columns()), 'opsi_f'];
        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Soal');
        $writer->addRow(Row::fromValues($columns));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(fn (string $column) => $row[$column] ?? '', $columns)));
        }

        $writer->close();

        return new UploadedFile($path, 'soal.xlsx', null, null, true);
    }

    private function import(array $rows)
    {
        return $this->post(route('admin.question-imports.store'), ['file' => $this->xlsx($rows)]);
    }

    public function test_all_question_types_are_imported()
    {
        $this->import([
            ['kategori' => 'logika', 'topik' => 'Umum', 'jenis' => 'pg', 'kesulitan' => 'mudah', 'pertanyaan' => "Baris satu\nbaris dua", 'bobot' => '2,5',
                'opsi_a' => 'A1', 'opsi_b' => 'B1', 'opsi_c' => 'C1', 'kunci' => 'b', 'penjelasan' => 'Karena B.'],
            ['kategori' => 'Logika', 'jenis' => 'pg_kompleks', 'kesulitan' => 'sedang', 'pertanyaan' => 'Prima?', 'bobot' => 10,
                'opsi_a' => '2', 'opsi_b' => '4', 'opsi_c' => '5', 'opsi_f' => '7', 'kunci' => 'A, C;F'],
            ['kategori' => 'Logika', 'jenis' => 'benar_salah', 'kesulitan' => 'sulit', 'pertanyaan' => 'Bumi datar.', 'bobot' => 5, 'kunci' => 'Salah'],
            ['kategori' => 'Logika', 'jenis' => 'isian', 'kesulitan' => 'easy', 'pertanyaan' => 'Ibu kota?', 'bobot' => 5, 'kunci' => 'Jakarta | DKI Jakarta'],
            ['kategori' => 'Logika', 'jenis' => 'esai', 'kesulitan' => 'hard', 'pertanyaan' => 'Jelaskan.', 'bobot' => 20, 'rubrik' => 'Runtut'],
            ['kode' => 'K1', 'kategori' => 'Logika', 'topik' => 'Kasus', 'jenis' => 'studi_kasus', 'kesulitan' => 'sedang', 'pertanyaan' => 'Teks <b>kasus</b>.'],
            ['induk' => 'k1', 'jenis' => 'pg', 'kesulitan' => 'sedang', 'pertanyaan' => 'Sub 1', 'bobot' => 3, 'opsi_a' => 'X', 'opsi_b' => 'Y', 'kunci' => 'A'],
            ['induk' => 'K1', 'jenis' => 'esai', 'kesulitan' => 'sedang', 'pertanyaan' => 'Sub 2', 'bobot' => 7],
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.questions.index'));

        $this->assertSame(6, Question::query()->topLevel()->count());
        $this->assertSame(8, Question::query()->count());

        $mc = Question::query()->where('type', QuestionType::MultipleChoice)->whereNull('parent_id')->sole();
        $this->assertSame($this->category->id, $mc->question_category_id);
        $this->assertSame('Umum', $mc->topic);
        $this->assertSame('2.50', $mc->default_points);
        $this->assertSame(Difficulty::Easy, $mc->difficulty);
        $this->assertStringContainsString('Baris satu<br />baris dua', $mc->body_html);
        $this->assertSame(['B1'], $mc->options()->where('is_correct', true)->pluck('body_html')->all());

        $ms = Question::query()->where('type', QuestionType::MultipleSelect)->sole();
        $this->assertSame(['2', '5', '7'], $ms->options()->where('is_correct', true)->orderBy('position')->pluck('body_html')->all());

        $tf = Question::query()->where('type', QuestionType::TrueFalse)->sole();
        $this->assertSame(['Salah'], $tf->options()->where('is_correct', true)->pluck('body_html')->all());

        $short = Question::query()->where('type', QuestionType::ShortAnswer)->sole();
        $this->assertSame(['Jakarta', 'DKI Jakarta'], $short->acceptedAnswers()->orderBy('id')->pluck('answer')->all());

        $case = Question::query()->where('type', QuestionType::CaseStudy)->sole();
        $this->assertStringNotContainsString('<b>', $case->body_html);
        $this->assertSame(['Sub 1', 'Sub 2'], $case->children()->orderBy('position')->get()->map(fn (Question $child) => strip_tags($child->body_html))->all());
        $this->assertSame('Kasus', $case->children()->first()->topic);

        $this->assertDatabaseHas('activity_logs', ['action' => 'question.imported']);
    }

    public function test_any_invalid_row_cancels_the_whole_import()
    {
        $this->import([
            ['kategori' => 'Logika', 'jenis' => 'esai', 'kesulitan' => 'sedang', 'pertanyaan' => 'Valid', 'bobot' => 5],
            ['kategori' => 'Tidak Ada', 'jenis' => 'pg', 'kesulitan' => 'mudah', 'pertanyaan' => 'X', 'bobot' => 5, 'opsi_a' => 'A', 'opsi_b' => 'B', 'kunci' => 'C'],
            ['kategori' => 'Logika', 'jenis' => 'pg', 'kesulitan' => 'aneh', 'pertanyaan' => 'Y', 'bobot' => 5, 'opsi_a' => 'A', 'opsi_b' => 'B', 'kunci' => 'A,B'],
            ['kategori' => 'Logika', 'jenis' => 'isian', 'kesulitan' => 'mudah', 'pertanyaan' => 'Z', 'bobot' => 0],
            ['induk' => 'K9', 'jenis' => 'esai', 'kesulitan' => 'mudah', 'pertanyaan' => 'Yatim', 'bobot' => 1],
        ])->assertSessionHasErrors(['rows.3', 'rows.4', 'rows.5', 'rows.6'])->assertSessionDoesntHaveErrors('rows.2');

        $this->assertSame(0, Question::query()->count());
    }

    public function test_errors_from_save_question_are_mapped_to_the_sub_question_row()
    {
        $this->import([
            ['kode' => 'K1', 'kategori' => 'Logika', 'jenis' => 'studi_kasus', 'kesulitan' => 'sedang', 'pertanyaan' => 'Kasus'],
            ['induk' => 'K1', 'jenis' => 'isian', 'kesulitan' => 'sedang', 'pertanyaan' => 'Sub', 'bobot' => 1, 'kunci' => 'jakarta|Jakarta'],
            ['kategori' => 'Logika', 'jenis' => 'esai', 'kesulitan' => 'sedang', 'pertanyaan' => 'Valid', 'bobot' => 5],
        ])->assertSessionHasErrors('rows.3')->assertSessionDoesntHaveErrors('rows.2');

        $this->assertSame(0, Question::query()->count());
    }

    public function test_case_study_without_sub_questions_is_rejected()
    {
        $this->import([
            ['kode' => 'K1', 'kategori' => 'Logika', 'jenis' => 'studi_kasus', 'kesulitan' => 'sedang', 'pertanyaan' => 'Kasus'],
        ])->assertSessionHasErrors('rows.2');
    }

    public function test_file_must_be_xlsx_with_required_columns()
    {
        $this->post(route('admin.question-imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('soal.csv', "kategori,jenis\n"),
        ])->assertSessionHasErrors('file');

        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['kategori', 'pertanyaan']));
        $writer->addRow(Row::fromValues(['Logika', 'Soal']));
        $writer->close();

        $this->post(route('admin.question-imports.store'), [
            'file' => new UploadedFile($path, 'soal.xlsx', null, null, true),
        ])->assertSessionHasErrors('file');
    }

    public function test_template_can_be_downloaded_and_its_examples_import_cleanly()
    {
        $response = $this->get(route('admin.question-imports.template'))->assertOk();
        $path = $response->getFile()->getPathname();
        $copy = tempnam(sys_get_temp_dir(), 'template').'.xlsx';
        copy($path, $copy);

        $reader = new Reader;
        $reader->open($copy);
        $names = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $names[] = $sheet->getName();
        }
        $reader->close();
        $this->assertSame(['Soal', 'Petunjuk', 'Kategori'], $names);

        $this->post(route('admin.question-imports.store'), [
            'file' => new UploadedFile($copy, 'template.xlsx', null, null, true),
        ])->assertSessionHasNoErrors();

        $this->assertSame(6, Question::query()->topLevel()->count());
    }

    public function test_only_staff_who_manage_exams_can_import()
    {
        foreach ([User::factory()->candidate()->create(), User::factory()->interviewer()->create()] as $user) {
            $this->actingAs($user);
            $this->get(route('admin.question-imports.template'))->assertNotFound();
            $this->import([])->assertNotFound();
        }

        $this->assertSame(0, Question::query()->count());
    }
}

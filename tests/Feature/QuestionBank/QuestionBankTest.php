<?php

namespace Tests\Feature\QuestionBank;

use App\Domain\Exams\Models\Exam;
use App\Domain\QuestionBank\Enums\QuestionStatus;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\QuestionBank\Models\QuestionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuestionBankTest extends TestCase
{
    use RefreshDatabase;

    private QuestionCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = QuestionCategory::factory()->create();
        $this->actingAs(User::factory()->hr()->create());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'category' => $this->category->public_id,
            'topic' => 'Produk',
            'type' => 'multiple_choice',
            'difficulty' => 'medium',
            'body_html' => '<p>Apa warna logo perusahaan?</p>',
            'explanation_html' => '<p>Biru.</p>',
            'default_points' => 10,
            'options' => [
                ['body' => 'Biru', 'is_correct' => true],
                ['body' => 'Merah', 'is_correct' => false],
                ['body' => 'Hijau', 'is_correct' => false],
            ],
            ...$overrides,
        ];
    }

    public function test_categories_can_be_managed()
    {
        $this->post(route('admin.question-categories.store'), ['name' => 'Logika'])->assertSessionHasNoErrors();
        $this->post(route('admin.question-categories.store'), ['name' => 'Logika'])->assertSessionHasErrors('name');
        $this->get(route('admin.question-categories.index'))->assertInertia(fn (Assert $page) => $page->has('categories', 2));
    }

    public function test_multiple_choice_questions_are_saved_with_sanitized_body_and_plain_options()
    {
        $this->post(route('admin.questions.store'), $this->payload([
            'body_html' => '<p onclick="x()">Apa <script>alert(1)</script>warna?</p>',
            'options' => [
                ['body' => '<b>Biru</b>', 'is_correct' => true],
                ['body' => 'Merah', 'is_correct' => false],
            ],
        ]))->assertSessionHasNoErrors();

        $question = Question::query()->sole();
        $this->assertSame(QuestionType::MultipleChoice, $question->type);
        $this->assertStringNotContainsString('script', $question->body_html);
        $this->assertStringNotContainsString('onclick', $question->body_html);
        $this->assertSame(['Biru', 'Merah'], $question->options()->pluck('body_html')->all());
        $this->assertSame(1, $question->version);
    }

    public function test_answer_rules_per_question_type()
    {
        $this->post(route('admin.questions.store'), $this->payload([
            'options' => [['body' => 'A', 'is_correct' => true], ['body' => 'B', 'is_correct' => true]],
        ]))->assertSessionHasErrors('options');

        $this->post(route('admin.questions.store'), $this->payload([
            'type' => 'multiple_select',
            'options' => [['body' => 'A', 'is_correct' => false], ['body' => 'B', 'is_correct' => false]],
        ]))->assertSessionHasErrors('options');

        $this->post(route('admin.questions.store'), $this->payload([
            'type' => 'short_answer', 'options' => [], 'accepted_answers' => ['Jakarta', '  jakarta '],
        ]))->assertSessionHasErrors('accepted_answers');

        $this->post(route('admin.questions.store'), $this->payload(['type' => 'case_study', 'options' => [], 'children' => []]))
            ->assertSessionHasErrors('children');

        $this->post(route('admin.questions.store'), $this->payload(['body_html' => '<p> </p>']))
            ->assertSessionHasErrors('body_html');

        $this->assertSame(0, Question::query()->count());
    }

    public function test_true_false_options_are_normalized()
    {
        $this->post(route('admin.questions.store'), $this->payload([
            'type' => 'true_false',
            'options' => [['body' => 'apa saja', 'is_correct' => false], ['body' => 'x', 'is_correct' => true]],
        ]))->assertSessionHasNoErrors();

        $options = Question::query()->sole()->options()->get();
        $this->assertSame(['Benar', 'Salah'], $options->pluck('body_html')->all());
        $this->assertSame([false, true], $options->pluck('is_correct')->all());
    }

    public function test_case_studies_have_sub_questions_and_no_own_points()
    {
        $this->post(route('admin.questions.store'), $this->payload([
            'type' => 'case_study',
            'options' => [],
            'default_points' => null,
            'children' => [
                [
                    'type' => 'multiple_choice', 'difficulty' => 'easy', 'body_html' => '<p>Sub 1</p>', 'default_points' => 5,
                    'options' => [['body' => 'Ya', 'is_correct' => true], ['body' => 'Tidak', 'is_correct' => false]],
                ],
                ['type' => 'essay', 'difficulty' => 'hard', 'body_html' => '<p>Sub 2</p>', 'default_points' => 15, 'rubric' => 'Jelaskan alasan'],
            ],
        ]))->assertSessionHasNoErrors();

        $case = Question::query()->topLevel()->sole();
        $this->assertSame('0.00', $case->default_points);
        $this->assertSame([QuestionType::MultipleChoice, QuestionType::Essay], $case->children()->pluck('type')->all());
        $this->assertSame('Jelaskan alasan', $case->children()->where('type', 'essay')->value('rubric'));

        $this->post(route('admin.questions.store'), $this->payload([
            'type' => 'case_study', 'options' => [], 'default_points' => null,
            'children' => [['type' => 'case_study', 'difficulty' => 'easy', 'body_html' => '<p>x</p>', 'default_points' => 1]],
        ]))->assertSessionHasErrors('children.0.type');
    }

    public function test_questions_not_used_by_locked_exams_are_edited_in_place()
    {
        $question = Question::factory()->multipleChoice()->create(['question_category_id' => $this->category->id]);
        Exam::factory()->withQuestions([$question])->create();

        $this->put(route('admin.questions.update', $question), $this->payload(['body_html' => '<p>Diubah</p>']))
            ->assertRedirect(route('admin.questions.edit', $question));

        $this->assertSame('<p>Diubah</p>', $question->refresh()->body_html);
        $this->assertSame(1, $question->version);
        $this->assertSame(1, Question::query()->count());
        $this->assertSame(3, $question->options()->count());
    }

    public function test_questions_used_by_locked_exams_get_a_new_version()
    {
        $question = Question::factory()->multipleChoice()->create(['question_category_id' => $this->category->id]);
        $lockedExam = Exam::factory()->locked()->withQuestions([$question])->create();
        $draftExam = Exam::factory()->withQuestions([$question])->create();
        $originalBody = $question->body_html;

        $this->put(route('admin.questions.update', $question), $this->payload(['body_html' => '<p>Versi baru</p>']))
            ->assertSessionHasNoErrors();

        $new = Question::query()->current()->topLevel()->sole();
        $this->assertNotSame($question->id, $new->id);
        $this->assertSame(2, $new->version);
        $this->assertSame($question->id, $new->previous_version_id);

        $question->refresh();
        $this->assertNotNull($question->superseded_at);
        $this->assertSame($originalBody, $question->body_html);

        // Ujian terkunci tetap memakai versi lama, ujian draf memakai versi baru.
        $this->assertSame($question->id, $lockedExam->examQuestions()->value('question_id'));
        $this->assertSame($new->id, $draftExam->examQuestions()->value('question_id'));

        $this->put(route('admin.questions.update', $question), $this->payload())->assertSessionHasErrors('question');

        $this->get(route('admin.questions.edit', $new))->assertInertia(fn (Assert $page) => $page
            ->where('lockedUsage', false)
            ->has('usage', 2));
    }

    public function test_used_questions_are_archived_instead_of_deleted()
    {
        $used = Question::factory()->create(['question_category_id' => $this->category->id]);
        Exam::factory()->withQuestions([$used])->create();
        $unused = Question::factory()->caseStudy()->create(['question_category_id' => $this->category->id]);

        $this->delete(route('admin.questions.destroy', $used));
        $this->assertModelExists($used);

        $this->delete(route('admin.questions.destroy', $unused))->assertRedirect(route('admin.questions.index'));
        $this->assertSame(1, Question::query()->count());

        $this->patch(route('admin.questions.status.update', $used), ['status' => 'archived'])->assertSessionHasNoErrors();
        $this->assertSame(QuestionStatus::Archived, $used->refresh()->status);
    }

    public function test_index_lists_current_top_level_questions_with_filters()
    {
        Question::factory()->count(2)->multipleChoice()->create(['question_category_id' => $this->category->id]);
        Question::factory()->essay()->create(['question_category_id' => $this->category->id]);
        Question::factory()->caseStudy()->create();
        Question::factory()->create(['superseded_at' => now()]);

        $this->get(route('admin.questions.index'))->assertInertia(fn (Assert $page) => $page->where('questions.meta.total', 4));
        $this->get(route('admin.questions.index', ['type' => 'essay']))->assertInertia(fn (Assert $page) => $page->where('questions.meta.total', 1));
        $this->get(route('admin.questions.index', ['category' => $this->category->public_id]))
            ->assertInertia(fn (Assert $page) => $page->where('questions.meta.total', 3));
    }

    public function test_sub_questions_cannot_be_edited_directly()
    {
        $child = Question::factory()->caseStudy()->create()->children()->first();

        $this->get(route('admin.questions.edit', $child))->assertNotFound();
    }
}

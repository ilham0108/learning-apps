<?php

namespace Tests\Feature\Database;

use App\Domain\Access\Models\DataDeletionRequest;
use App\Domain\Candidates\Models\CandidateEvaluation;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\Department;
use App\Domain\Candidates\Models\InternalNote;
use App\Domain\Candidates\Models\Position;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Exams\Models\AttemptAnswer;
use App\Domain\Exams\Models\AttemptQuestion;
use App\Domain\Exams\Models\AttemptQuestionOption;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\LearningModule;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Models\MaterialProgress;
use App\Domain\Learning\Models\ProgramAssignment;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\QuestionBank\Models\QuestionCategory;
use App\Domain\QuestionBank\Models\QuestionOption;
use App\Domain\Reporting\Models\ReportExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function models(): array
    {
        return collect([
            User::class, Department::class, Position::class, RecruitmentCampaign::class, CandidateProfile::class,
            RecruitmentApplication::class, InternalNote::class, CandidateEvaluation::class, DataDeletionRequest::class,
            LearningProgram::class, LearningModule::class, LearningMaterial::class, ProgramAssignment::class,
            MaterialProgress::class, QuestionCategory::class, Question::class, QuestionOption::class, Exam::class,
            ExamAssignment::class, ExamAttempt::class, AttemptQuestion::class, AttemptQuestionOption::class,
            AttemptAnswer::class, ReportExport::class,
        ])->mapWithKeys(fn (string $class) => [class_basename($class) => [$class]])->all();
    }

    /**
     * @param  class-string<Model>  $class
     */
    #[DataProvider('models')]
    public function test_factory_creates_a_persisted_model(string $class)
    {
        $model = $class::factory()->create();

        $this->assertTrue($model->exists);
        $this->assertModelExists($model);
    }

    public function test_question_factory_creates_valid_content_for_every_type()
    {
        $multipleChoice = Question::factory()->multipleChoice()->create();
        $this->assertSame(4, $multipleChoice->options()->count());
        $this->assertSame(1, $multipleChoice->options()->where('is_correct', true)->count());

        $multipleSelect = Question::factory()->multipleSelect()->create();
        $this->assertSame(2, $multipleSelect->options()->where('is_correct', true)->count());

        $trueFalse = Question::factory()->trueFalse()->create();
        $this->assertSame(['Benar', 'Salah'], $trueFalse->options()->pluck('body_html')->all());

        $shortAnswer = Question::factory()->shortAnswer()->create();
        $this->assertSame(1, $shortAnswer->acceptedAnswers()->count());

        $essay = Question::factory()->essay()->create();
        $this->assertSame(0, $essay->options()->count());
        $this->assertNotNull($essay->rubric);

        $caseStudy = Question::factory()->caseStudy()->create();
        $this->assertSame('0.00', $caseStudy->default_points);
        $this->assertSame(
            [QuestionType::MultipleChoice, QuestionType::Essay],
            $caseStudy->children()->get()->pluck('type')->all(),
        );
        $this->assertSame(0, Question::query()->current()->topLevel()->whereKey($caseStudy->children()->pluck('id'))->count());
    }
}

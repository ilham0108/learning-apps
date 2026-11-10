<?php

namespace App\Providers;

use App\Domain\Access\Models\DataDeletionRequest;
use App\Domain\Candidates\Models\CandidateEvaluation;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\InternalNote;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Exams\Models\AttemptAnswer;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Exams\Models\ExamAttempt;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\Reporting\Models\ReportExport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Relation::enforceMorphMap([
            'user' => User::class,
            'candidate_profile' => CandidateProfile::class,
            'recruitment_campaign' => RecruitmentCampaign::class,
            'recruitment_application' => RecruitmentApplication::class,
            'internal_note' => InternalNote::class,
            'candidate_evaluation' => CandidateEvaluation::class,
            'learning_program' => LearningProgram::class,
            'learning_material' => LearningMaterial::class,
            'question' => Question::class,
            'exam' => Exam::class,
            'exam_assignment' => ExamAssignment::class,
            'exam_attempt' => ExamAttempt::class,
            'attempt_answer' => AttemptAnswer::class,
            'report_export' => ReportExport::class,
            'data_deletion_request' => DataDeletionRequest::class,
        ]);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

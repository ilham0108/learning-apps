<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 150)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'name']);
        });

        Schema::create('recruitment_campaigns', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->foreignId('position_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 20);
            $table->dateTime('closed_at')->nullable();
            $table->dateTime('anonymized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'starts_on']);
        });

        Schema::create('campaign_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['recruitment_campaign_id', 'user_id']);
        });

        Schema::create('candidate_profiles', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('candidate_number', 20)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('last_education', 100)->nullable();
            $table->date('registered_on');
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('consent_given_at')->nullable();
            $table->string('consent_policy_version', 20)->nullable();
            $table->dateTime('anonymized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('candidate_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number');
        });

        Schema::create('recruitment_applications', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('candidate_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('recruitment_campaign_id')->constrained()->restrictOnDelete();
            $table->string('stage', 20);
            $table->dateTime('stage_changed_at');
            $table->dateTime('applied_at');
            $table->timestamps();

            $table->unique(['candidate_profile_id', 'recruitment_campaign_id'], 'applications_candidate_campaign_unique');
            $table->index(['recruitment_campaign_id', 'stage']);
        });

        Schema::create('application_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_application_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage', 20)->nullable();
            $table->string('to_stage', 20);
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('internal_notes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('recruitment_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('candidate_evaluations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('recruitment_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recommendation', 20)->nullable();
            $table->text('summary')->nullable();
            $table->string('status', 20);
            $table->dateTime('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['recruitment_application_id', 'evaluator_id'], 'evaluations_application_evaluator_unique');
        });

        Schema::create('candidate_evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_evaluation_id')->constrained()->cascadeOnDelete();
            $table->string('criterion', 20);
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['candidate_evaluation_id', 'criterion'], 'evaluation_scores_criterion_unique');
        });

        Schema::create('data_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->string('status', 20)->index();
            $table->text('decision_note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_deletion_requests');
        Schema::dropIfExists('candidate_evaluation_scores');
        Schema::dropIfExists('candidate_evaluations');
        Schema::dropIfExists('internal_notes');
        Schema::dropIfExists('application_stage_histories');
        Schema::dropIfExists('recruitment_applications');
        Schema::dropIfExists('candidate_number_sequences');
        Schema::dropIfExists('candidate_profiles');
        Schema::dropIfExists('campaign_user');
        Schema::dropIfExists('recruitment_campaigns');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};

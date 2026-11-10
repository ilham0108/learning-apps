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
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            // MySQL 5.7 tidak mengizinkan CASCADE pada kolom dasar generated column.
            $table->foreignId('exam_assignment_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->string('status', 20);
            $table->string('grading_status', 20)->nullable();
            $table->string('result', 10)->nullable();
            // Pengganti partial unique index: hanya terisi selama attempt berjalan,
            // sehingga satu penugasan tidak bisa memiliki dua attempt aktif.
            $table->unsignedBigInteger('active_assignment_id')
                ->nullable()
                ->storedAs("IF(`status` = 'in_progress', `exam_assignment_id`, NULL)")
                ->unique();
            $table->dateTime('started_at');
            $table->dateTime('deadline_at');
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->string('session_token_hash', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedSmallInteger('furthest_position')->default(0);
            $table->decimal('earned_points', 10, 2)->nullable();
            $table->decimal('max_points', 10, 2);
            $table->decimal('score_percent', 6, 2)->nullable();
            $table->decimal('passing_grade', 5, 2);
            $table->string('navigation', 20);
            $table->string('score_policy', 20);
            $table->string('result_release', 20);
            $table->timestamps();

            $table->unique(['exam_assignment_id', 'attempt_number']);
            $table->index(['status', 'deadline_at']);
            $table->index('grading_status');
        });

        Schema::table('exam_assignments', function (Blueprint $table) {
            $table->foreign('effective_attempt_id')->references('id')->on('exam_attempts')->nullOnDelete();
        });

        Schema::create('attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->ulid('token')->unique();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('attempt_questions')->cascadeOnDelete();
            $table->foreignId('source_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->foreignId('question_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_name', 150);
            $table->unsignedSmallInteger('position');
            $table->string('type', 20);
            $table->longText('body_html');
            $table->text('explanation_html')->nullable();
            $table->text('rubric')->nullable();
            $table->decimal('points', 8, 2);
            $table->string('scoring_rule', 20);
            $table->json('accepted_answers')->nullable();
            $table->timestamps();

            $table->index(['exam_attempt_id', 'position']);
        });

        Schema::create('attempt_question_options', function (Blueprint $table) {
            $table->id();
            $table->ulid('token')->unique();
            $table->foreignId('attempt_question_id')->constrained()->cascadeOnDelete();
            $table->text('body_html');
            $table->boolean('is_correct');
            $table->unsignedSmallInteger('position');

            $table->index(['attempt_question_id', 'position']);
        });

        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_question_id')->constrained()->cascadeOnDelete();
            $table->longText('text_answer')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->unsignedInteger('client_revision')->default(0);
            $table->dateTime('saved_at')->nullable();
            $table->decimal('auto_points', 8, 2)->nullable();
            $table->decimal('manual_points', 8, 2)->nullable();
            $table->decimal('awarded_points', 8, 2)->nullable();
            $table->string('grading_status', 20)->nullable()->index();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('graded_at')->nullable();
            $table->text('grader_note')->nullable();
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'attempt_question_id']);
        });

        Schema::create('attempt_answer_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_answer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_question_option_id')->constrained()->cascadeOnDelete();

            $table->unique(['attempt_answer_id', 'attempt_question_option_id'], 'attempt_answer_options_unique');
        });

        Schema::create('attempt_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->dateTime('occurred_at');

            $table->index(['exam_attempt_id', 'occurred_at']);
        });

        Schema::create('attempt_category_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_name', 150);
            $table->decimal('earned_points', 10, 2);
            $table->decimal('max_points', 10, 2);
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'category_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attempt_category_scores');
        Schema::dropIfExists('attempt_events');
        Schema::dropIfExists('attempt_answer_options');
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('attempt_question_options');
        Schema::dropIfExists('attempt_questions');

        Schema::table('exam_assignments', function (Blueprint $table) {
            $table->dropForeign(['effective_attempt_id']);
        });

        Schema::dropIfExists('exam_attempts');
    }
};

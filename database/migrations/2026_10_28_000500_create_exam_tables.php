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
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 30);
            $table->unsignedSmallInteger('duration_minutes');
            $table->decimal('passing_grade', 5, 2);
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->string('navigation', 20);
            $table->string('score_policy', 20);
            $table->string('multi_select_scoring', 20);
            $table->string('result_release', 20);
            $table->dateTime('results_released_at')->nullable();
            $table->boolean('show_review')->default(false);
            $table->string('status', 20);
            $table->dateTime('locked_at')->nullable();
            $table->foreignId('duplicated_from_id')->nullable()->constrained('exams')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'closes_at']);
        });

        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->decimal('points', 8, 2);
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['exam_id', 'question_id']);
        });

        Schema::create('exam_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_program_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'learning_program_id']);
        });

        Schema::create('program_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['learning_program_id', 'exam_id']);
        });

        Schema::create('exam_assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('exam_id')->constrained()->restrictOnDelete();
            $table->foreignId('recruitment_application_id')->constrained()->restrictOnDelete();
            $table->string('assigned_via', 20);
            $table->dateTime('opens_at_override')->nullable();
            $table->dateTime('closes_at_override')->nullable();
            $table->unsignedTinyInteger('max_attempts_override')->nullable();
            // Foreign key ke exam_attempts ditambahkan di migration attempt.
            $table->unsignedBigInteger('effective_attempt_id')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'recruitment_application_id']);
            $table->index('recruitment_application_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_assignments');
        Schema::dropIfExists('program_exams');
        Schema::dropIfExists('exam_prerequisites');
        Schema::dropIfExists('exam_questions');
        Schema::dropIfExists('exams');
    }
};

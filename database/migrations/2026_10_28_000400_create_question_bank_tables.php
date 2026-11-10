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
        Schema::create('question_categories', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('question_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('questions')->restrictOnDelete();
            $table->foreignId('previous_version_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('topic', 150)->nullable();
            $table->string('difficulty', 10);
            $table->string('type', 20);
            $table->longText('body_html');
            $table->text('explanation_html')->nullable();
            $table->text('rubric')->nullable();
            $table->decimal('default_points', 8, 2);
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('status', 20);
            $table->dateTime('superseded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'superseded_at', 'question_category_id', 'difficulty', 'type'], 'questions_filter_index');
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('body_html');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['question_id', 'position']);
        });

        Schema::create('question_accepted_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('answer');
            $table->string('normalized');
            $table->timestamps();

            $table->unique(['question_id', 'normalized']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_accepted_answers');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('question_categories');
    }
};

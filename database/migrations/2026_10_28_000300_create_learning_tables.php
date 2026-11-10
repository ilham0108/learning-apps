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
        Schema::create('learning_programs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->foreignId('target_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('learning_modules', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('learning_program_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['learning_program_id', 'position']);
        });

        Schema::create('learning_materials', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('learning_module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 20);
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('position');
            $table->longText('body_html')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->string('file_mime', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('external_url')->nullable();
            $table->string('video_provider', 20)->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->timestamps();

            $table->index(['learning_module_id', 'position']);
        });

        Schema::create('program_assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('learning_program_id')->constrained()->restrictOnDelete();
            $table->foreignId('recruitment_application_id')->constrained()->restrictOnDelete();
            $table->string('assigned_via', 20);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->timestamps();

            $table->unique(['learning_program_id', 'recruitment_application_id'], 'program_assignments_program_application_unique');
        });

        Schema::create('material_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_material_id')->constrained()->restrictOnDelete();
            $table->dateTime('first_opened_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('watched_seconds')->default(0);
            $table->timestamps();

            $table->unique(['candidate_profile_id', 'learning_material_id'], 'material_progress_candidate_material_unique');
            $table->index(['learning_material_id', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_progress');
        Schema::dropIfExists('program_assignments');
        Schema::dropIfExists('learning_materials');
        Schema::dropIfExists('learning_modules');
        Schema::dropIfExists('learning_programs');
    }
};

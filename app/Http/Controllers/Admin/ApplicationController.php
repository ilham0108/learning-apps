<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Candidates\Actions\ChangeApplicationStage;
use App\Domain\Candidates\Actions\SaveEvaluation;
use App\Domain\Candidates\Enums\Recommendation;
use App\Domain\Candidates\Enums\RecruitmentStage;
use App\Domain\Candidates\Models\InternalNote;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeStageRequest;
use App\Http\Requests\Admin\EvaluationRequest;
use App\Http\Requests\Admin\InternalNoteRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Tindakan HR pada satu lamaran: tahap rekrutmen, catatan internal, dan evaluasi.
 */
class ApplicationController extends Controller
{
    public function updateStage(ChangeStageRequest $request, RecruitmentApplication $application, ChangeApplicationStage $changeStage): RedirectResponse
    {
        $this->authorize('manage', $application);

        $changeStage(
            $application,
            $request->enum('stage', RecruitmentStage::class),
            $request->validated('reason'),
            $request->user(),
        );

        return $this->saved(__('Tahap rekrutmen berhasil diperbarui.'));
    }

    public function storeNote(InternalNoteRequest $request, RecruitmentApplication $application): RedirectResponse
    {
        $this->authorize('manage', $application);

        $application->internalNotes()->create([
            'author_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return $this->saved(__('Catatan berhasil ditambahkan.'));
    }

    public function updateNote(InternalNoteRequest $request, InternalNote $note): RedirectResponse
    {
        $this->authorize('update', $note);

        $note->update(['body' => $request->validated('body')]);

        return $this->saved(__('Catatan berhasil diperbarui.'));
    }

    public function destroyNote(InternalNote $note, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('delete', $note);

        $logger->log('internal_note.deleted', $note->application, ['note' => $note->public_id]);
        $note->delete();

        return $this->saved(__('Catatan berhasil dihapus.'));
    }

    public function updateEvaluation(EvaluationRequest $request, RecruitmentApplication $application, SaveEvaluation $saveEvaluation): RedirectResponse
    {
        $this->authorize('manage', $application);

        $finalize = $request->boolean('finalize');

        $saveEvaluation(
            $application,
            $request->user(),
            $request->scores(),
            $request->enum('recommendation', Recommendation::class),
            $request->validated('summary'),
            $finalize,
        );

        return $this->saved($finalize ? __('Evaluasi berhasil difinalkan.') : __('Draf evaluasi tersimpan.'));
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}

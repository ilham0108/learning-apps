<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Learning\Actions\AssignProgram;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Models\ProgramAssignment;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignmentTargetsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProgramAssignmentController extends Controller
{
    public function store(AssignmentTargetsRequest $request, LearningProgram $program, AssignProgram $assignProgram): RedirectResponse
    {
        $this->authorize('assign', $program);

        $created = $assignProgram($program, $request->targets(), $request->source(), $request->dueAt(), $request->user());

        Inertia::flash('toast', [
            'type' => $created > 0 ? 'success' : 'info',
            'message' => $created > 0
                ? __('Program ditugaskan ke :count kandidat.', ['count' => $created])
                : __('Tidak ada kandidat baru yang ditugaskan. Kandidat mungkin sudah ditugaskan atau tahapnya sudah selesai.'),
        ]);

        return back();
    }

    /**
     * Mencabut penugasan. Progres per materi tetap tersimpan pada kandidat.
     */
    public function destroy(Request $request, ProgramAssignment $assignment, ActivityLogger $logger): RedirectResponse
    {
        abort_unless(
            RecruitmentApplication::query()->visibleTo($request->user())->whereKey($assignment->recruitment_application_id)->exists(),
            404,
        );
        $this->authorize('update', $assignment->program);

        $logger->log('program.unassigned', $assignment->program, ['application' => $assignment->application->public_id]);
        $assignment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Penugasan program dicabut.')]);

        return back();
    }
}

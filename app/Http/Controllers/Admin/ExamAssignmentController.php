<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Exams\Actions\AssignExam;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamAssignment;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignmentTargetsRequest;
use App\Http\Requests\Admin\ExamAssignmentScheduleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExamAssignmentController extends Controller
{
    public function store(AssignmentTargetsRequest $request, Exam $exam, AssignExam $assignExam): RedirectResponse
    {
        $this->authorize('assign', $exam);

        $created = $assignExam($exam, $request->targets(), $request->source(), $request->user());

        Inertia::flash('toast', [
            'type' => $created > 0 ? 'success' : 'info',
            'message' => $created > 0
                ? __('Ujian ditugaskan ke :count kandidat.', ['count' => $created])
                : __('Tidak ada kandidat baru yang ditugaskan. Kandidat mungkin sudah ditugaskan atau tahapnya sudah selesai.'),
        ]);

        return back();
    }

    /**
     * Jadwal khusus individu, misalnya perpanjangan waktu tutup atau tambahan percobaan.
     */
    public function update(ExamAssignmentScheduleRequest $request, ExamAssignment $assignment, ActivityLogger $logger): RedirectResponse
    {
        $this->ensureInScope($request, $assignment);
        $this->authorize('update', $assignment->exam);

        $assignment->update($request->scheduleData());
        $logger->log('exam_assignment.schedule_changed', $assignment, [
            'opens_at' => $assignment->opens_at_override?->toIso8601String(),
            'closes_at' => $assignment->closes_at_override?->toIso8601String(),
            'max_attempts' => $assignment->max_attempts_override,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Jadwal khusus disimpan.')]);

        return back();
    }

    /**
     * Penugasan yang sudah memiliki attempt tidak dapat dicabut agar riwayat tetap utuh.
     */
    public function destroy(Request $request, ExamAssignment $assignment, ActivityLogger $logger): RedirectResponse
    {
        $this->ensureInScope($request, $assignment);
        $this->authorize('update', $assignment->exam);

        if ($assignment->attempts()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Penugasan yang sudah dikerjakan tidak dapat dicabut.')]);

            return back();
        }

        $logger->log('exam.unassigned', $assignment->exam, ['application' => $assignment->application->public_id]);
        $assignment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Penugasan ujian dicabut.')]);

        return back();
    }

    private function ensureInScope(Request $request, ExamAssignment $assignment): void
    {
        abort_unless(
            RecruitmentApplication::query()->visibleTo($request->user())->whereKey($assignment->recruitment_application_id)->exists(),
            404,
        );
    }
}

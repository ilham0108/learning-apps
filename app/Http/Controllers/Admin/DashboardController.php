<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Candidates\Models\Department;
use App\Domain\Candidates\Models\Position;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Reporting\Queries\HrDashboard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DashboardFilterRequest;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda Super Admin dan HR: angka dalam lingkup rekrutmen pengguna,
 * dapat difilter per periode, posisi, departemen, rekrutmen, program, dan tahap.
 */
class DashboardController extends Controller
{
    public function __invoke(DashboardFilterRequest $request, HrDashboard $dashboard): Response
    {
        $user = $request->user();

        return Inertia::render('admin/dashboard', [
            ...$dashboard->for($user, $request->resolved()),
            'filters' => $request->filters(),
            'options' => [
                'campaigns' => RecruitmentCampaign::query()->visibleTo($user)->orderByDesc('starts_on')->get()
                    ->map(fn (RecruitmentCampaign $campaign) => ['id' => $campaign->public_id, 'name' => $campaign->name])->all(),
                'positions' => Position::query()->orderBy('name')->get()
                    ->map(fn (Position $position) => ['id' => $position->public_id, 'name' => $position->name])->all(),
                'departments' => Department::query()->orderBy('name')->get()
                    ->map(fn (Department $department) => ['id' => $department->public_id, 'name' => $department->name])->all(),
                'programs' => LearningProgram::query()->orderBy('name')->get()
                    ->map(fn (LearningProgram $program) => ['id' => $program->public_id, 'name' => $program->name])->all(),
                'periods' => DashboardFilterRequest::PERIODS,
            ],
        ]);
    }
}

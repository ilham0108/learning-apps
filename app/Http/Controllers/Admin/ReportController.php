<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Candidates\Models\Department;
use App\Domain\Candidates\Models\Position;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Domain\Exams\Models\Exam;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Reporting\Queries\ApplicationScope;
use App\Domain\Reporting\Reports\Report;
use App\Domain\Reporting\Reports\ReportRegistry;
use App\Domain\Reporting\Support\ReportFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportFilterRequest;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman laporan: pilih jenis laporan, atur filter, lihat pratinjau, lalu
 * ekspor CSV. Pratinjau memakai query yang sama dengan file ekspor.
 */
class ReportController extends Controller
{
    private const PREVIEW_PER_PAGE = 25;

    public function index(ReportFilterRequest $request): Response
    {
        $user = $request->user();
        $report = ReportRegistry::for($request->reportType());
        $filters = $request->filters();
        $resolved = ReportFilters::resolve($filters, $user);

        $page = $report->query(ApplicationScope::ids($user, $resolved), $resolved)
            ->paginate(self::PREVIEW_PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/reports/index', [
            'reports' => array_map(fn (Report $item) => [
                'type' => $item->type()->value,
                'title' => $item->title(),
                'description' => $item->description(),
                'extra_filters' => $item->extraFilters(),
            ], ReportRegistry::all()),
            'report' => $report->type()->value,
            'columns' => collect($report->columns())->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'rows' => [
                'data' => $page->getCollection()->map(fn (object $row) => $report->row($row))->values()->all(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                    'from' => $page->firstItem(),
                    'to' => $page->lastItem(),
                ],
            ],
            'filters' => [
                'period' => isset($filters['period']) ? (int) $filters['period'] : null,
                ...array_fill_keys(['campaign', 'position', 'department', 'program', 'exam', 'stage'], null),
                ...array_diff_key($filters, ['period' => true]),
            ],
            'options' => [
                'campaigns' => RecruitmentCampaign::query()->visibleTo($user)->orderByDesc('starts_on')->get()
                    ->map(fn (RecruitmentCampaign $campaign) => ['id' => $campaign->public_id, 'name' => $campaign->name])->all(),
                'positions' => Position::query()->orderBy('name')->get()
                    ->map(fn (Position $position) => ['id' => $position->public_id, 'name' => $position->name])->all(),
                'departments' => Department::query()->orderBy('name')->get()
                    ->map(fn (Department $department) => ['id' => $department->public_id, 'name' => $department->name])->all(),
                'programs' => LearningProgram::query()->orderBy('name')->get()
                    ->map(fn (LearningProgram $program) => ['id' => $program->public_id, 'name' => $program->name])->all(),
                'exams' => Exam::query()->orderBy('title')->get()
                    ->map(fn (Exam $exam) => ['id' => $exam->public_id, 'name' => $exam->title])->all(),
                'periods' => ReportFilters::PERIODS,
            ],
        ]);
    }
}

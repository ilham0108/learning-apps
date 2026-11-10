<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Candidates\Models\Department;
use App\Domain\Candidates\Models\Position;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepartmentRequest;
use App\Http\Requests\Admin\PositionRequest;
use App\Http\Resources\Candidates\DepartmentResource;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Data master departemen dan posisi (Super Admin dan HR). Data yang sudah dipakai
 * dinonaktifkan, bukan dihapus, agar riwayat gelombang tetap utuh.
 */
class PositionController extends Controller
{
    public function index(): Response
    {
        $departments = Department::query()
            ->with(['positions' => fn ($query) => $query->withCount('campaigns')->orderBy('name')])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/positions/index', [
            'departments' => DepartmentResource::collection($departments)->resolve(),
        ]);
    }

    public function storeDepartment(DepartmentRequest $request): RedirectResponse
    {
        Department::query()->create($request->validated());

        return $this->saved();
    }

    public function updateDepartment(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return $this->saved();
    }

    public function storePosition(PositionRequest $request): RedirectResponse
    {
        $request->department()?->positions()->create($request->safe()->only(['name', 'is_active']));

        return $this->saved();
    }

    public function updatePosition(PositionRequest $request, Position $position): RedirectResponse
    {
        $position->update($request->safe()->only(['name', 'is_active']));

        return $this->saved();
    }

    private function saved(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Data berhasil disimpan.')]);

        return back();
    }
}

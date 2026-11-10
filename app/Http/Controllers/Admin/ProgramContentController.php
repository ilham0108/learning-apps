<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Learning\Actions\RemoveMaterial;
use App\Domain\Learning\Actions\SaveMaterial;
use App\Domain\Learning\Enums\MaterialType;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\LearningModule;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Support\MaterialContent;
use App\Domain\Shared\Support\Reorder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MaterialRequest;
use App\Http\Requests\Admin\ModuleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menyusun isi program: modul dan materi berurutan.
 */
class ProgramContentController extends Controller
{
    public function storeModule(ModuleRequest $request, LearningProgram $program): RedirectResponse
    {
        $this->authorize('update', $program);

        $program->modules()->create([
            ...$request->validated(),
            'position' => (int) $program->modules()->max('position') + 1,
        ]);

        return $this->saved(__('Modul berhasil ditambahkan.'));
    }

    public function updateModule(ModuleRequest $request, LearningModule $module): RedirectResponse
    {
        $this->authorize('update', $module->program);

        $module->update($request->validated());

        return $this->saved(__('Modul berhasil diperbarui.'));
    }

    /**
     * Modul hanya dapat dihapus jika kosong; materi yang sudah dipelajari diarsipkan, bukan dihapus.
     */
    public function destroyModule(LearningModule $module): RedirectResponse
    {
        $this->authorize('update', $module->program);

        if ($module->materials()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Hapus atau pindahkan semua materi sebelum menghapus modul.')]);

            return back();
        }

        $module->delete();

        return $this->saved(__('Modul berhasil dihapus.'));
    }

    public function moveModule(Request $request, LearningModule $module): RedirectResponse
    {
        $this->authorize('update', $module->program);

        Reorder::move(
            LearningModule::query()->where('learning_program_id', $module->learning_program_id),
            $module,
            $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'],
        );

        return back();
    }

    public function createMaterial(Request $request, LearningModule $module): Response
    {
        $this->authorize('update', $module->program);

        return Inertia::render('admin/programs/material-form', [
            'program' => ['id' => $module->program->public_id, 'name' => $module->program->name],
            'module' => ['id' => $module->public_id, 'title' => $module->title],
            'material' => null,
            'initialType' => $request->enum('type', MaterialType::class)->value ?? MaterialType::Article->value,
        ]);
    }

    public function storeMaterial(MaterialRequest $request, LearningModule $module, SaveMaterial $saveMaterial): RedirectResponse
    {
        $this->authorize('update', $module->program);

        $saveMaterial($module, null, $request->materialData(), $request->file('file'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Materi berhasil ditambahkan.')]);

        return to_route('admin.programs.show', $module->program);
    }

    public function editMaterial(LearningMaterial $material): Response
    {
        $this->authorize('update', $material);

        $module = $material->module;

        return Inertia::render('admin/programs/material-form', [
            'program' => ['id' => $module->program->public_id, 'name' => $module->program->name],
            'module' => ['id' => $module->public_id, 'title' => $module->title],
            'material' => [
                'id' => $material->public_id,
                'title' => $material->title,
                'type' => $material->type->value,
                'is_required' => $material->is_required,
                'is_archived' => $material->archived_at !== null,
                ...MaterialContent::for($material),
                // Untuk form ubah, tautan video juga dikirim apa adanya.
                'external_url' => $material->external_url,
            ],
            'initialType' => $material->type->value,
        ]);
    }

    public function updateMaterial(MaterialRequest $request, LearningMaterial $material, SaveMaterial $saveMaterial): RedirectResponse
    {
        $this->authorize('update', $material);

        $saveMaterial($material->module, $material, $request->materialData(), $request->file('file'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Materi berhasil diperbarui.')]);

        return to_route('admin.programs.show', $material->module->program);
    }

    public function destroyMaterial(Request $request, LearningMaterial $material, RemoveMaterial $removeMaterial): RedirectResponse
    {
        $this->authorize('update', $material);

        $result = $removeMaterial($material, $request->user());

        return $this->saved($result === 'archived'
            ? __('Materi sudah dipelajari kandidat sehingga diarsipkan, bukan dihapus.')
            : __('Materi berhasil dihapus.'));
    }

    public function restoreMaterial(LearningMaterial $material): RedirectResponse
    {
        $this->authorize('update', $material);

        $material->forceFill(['archived_at' => null])->save();

        return $this->saved(__('Materi diaktifkan kembali.'));
    }

    public function moveMaterial(Request $request, LearningMaterial $material): RedirectResponse
    {
        $this->authorize('update', $material);

        Reorder::move(
            LearningMaterial::query()->where('learning_module_id', $material->learning_module_id),
            $material,
            $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'],
        );

        return back();
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}

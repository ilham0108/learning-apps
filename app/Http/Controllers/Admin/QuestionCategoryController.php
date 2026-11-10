<?php

namespace App\Http\Controllers\Admin;

use App\Domain\QuestionBank\Models\QuestionCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kategori soal (data master bersama untuk semua HR).
 */
class QuestionCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/question-categories/index', [
            'categories' => QuestionCategory::query()
                ->withCount(['questions' => fn ($query) => $query->current()->topLevel()])
                ->orderBy('name')
                ->get()
                ->map(fn (QuestionCategory $category) => [
                    'id' => $category->public_id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'questions_count' => $category->questions_count ?? 0,
                ])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        QuestionCategory::query()->create($this->validated($request, null));

        return $this->saved();
    }

    public function update(Request $request, QuestionCategory $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return $this->saved();
    }

    /**
     * @return array{name: string, description: string|null}
     */
    private function validated(Request $request, ?QuestionCategory $category): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique(QuestionCategory::class)->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['name' => 'nama kategori', 'description' => 'deskripsi']);

        return ['name' => trim($data['name']), 'description' => $data['description'] ?? null];
    }

    private function saved(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Data berhasil disimpan.')]);

        return back();
    }
}

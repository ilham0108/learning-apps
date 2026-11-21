<?php

namespace App\Http\Controllers\Admin;

use App\Domain\QuestionBank\Actions\ImportQuestions;
use App\Domain\QuestionBank\Import\QuestionSheetReader;
use App\Domain\QuestionBank\Import\QuestionTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Impor bank soal dari file Excel (.xlsx) beserta unduhan templatenya.
 */
class QuestionImportController extends Controller
{
    public function template(QuestionTemplate $template): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'question-template');
        $template->write($path);

        return response()->download($path, 'template-bank-soal.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function store(Request $request, QuestionSheetReader $reader, ImportQuestions $import): RedirectResponse
    {
        $request->validate(
            ['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']],
            ['file.mimes' => __('File harus berformat .xlsx.')],
            ['file' => 'file'],
        );

        $count = $import($reader->read($request->file('file')->getRealPath()), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':count soal berhasil diimpor.', ['count' => $count])]);

        return to_route('admin.questions.index');
    }
}

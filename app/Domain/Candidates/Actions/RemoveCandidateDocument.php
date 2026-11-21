<?php

namespace App\Domain\Candidates\Actions;

use App\Domain\Candidates\Models\CandidateDocument;
use Illuminate\Support\Facades\Storage;

/**
 * Menghapus dokumen beserta file-nya. Data dihapus lebih dulu agar file yang
 * masih dirujuk tidak pernah hilang.
 */
class RemoveCandidateDocument
{
    public function __invoke(CandidateDocument $document): void
    {
        $path = $document->file_path;
        $document->delete();

        Storage::disk(CandidateDocument::DISK)->delete($path);
    }
}

<?php

namespace App\Http\Controllers;

use App\Domain\Candidates\Models\CandidateDocument;
use App\Domain\Shared\Audit\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Menyajikan dokumen kandidat dari disk privat: wajib login, URL bertanda
 * tangan, dan izin Policy. Setiap kali staf membuka dokumen identitas (KTP),
 * aksinya dicatat di audit log.
 */
class CandidateDocumentFileController extends Controller
{
    public function __invoke(Request $request, CandidateDocument $document, ActivityLogger $logger): BinaryFileResponse
    {
        $this->authorize('view', $document);

        $disk = Storage::disk(CandidateDocument::DISK);
        abort_unless($disk->exists($document->file_path), 404);

        if ($document->type->isSensitive() && ! $request->user()->isCandidate()) {
            $logger->log('candidate_document.viewed', $document->profile, ['type' => $document->type->value]);
        }

        return response()->file($disk->path($document->file_path), [
            'Content-Type' => $document->file_mime,
            'Content-Disposition' => 'inline; filename="'.addcslashes($document->file_original_name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

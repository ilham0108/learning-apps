<?php

namespace App\Http\Controllers;

use App\Domain\Learning\Actions\SaveMaterial;
use App\Domain\Learning\Models\LearningMaterial;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Menyajikan file materi dari disk privat. Membutuhkan login, URL bertanda tangan
 * yang masih berlaku, dan izin Policy. BinaryFileResponse mendukung Range sehingga
 * video MP4 dapat diputar dan digeser.
 */
class MaterialFileController extends Controller
{
    public function __invoke(LearningMaterial $material): BinaryFileResponse
    {
        $this->authorize('view', $material);

        $disk = Storage::disk(SaveMaterial::DISK);
        abort_if($material->file_path === null || ! $disk->exists($material->file_path), 404);

        return response()->file($disk->path($material->file_path), [
            'Content-Type' => $material->file_mime ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addcslashes((string) $material->file_original_name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

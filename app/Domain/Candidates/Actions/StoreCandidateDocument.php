<?php

namespace App\Domain\Candidates\Actions;

use App\Domain\Candidates\Enums\CandidateDocumentType;
use App\Domain\Candidates\Models\CandidateDocument;
use App\Domain\Candidates\Models\CandidateProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Menyimpan dokumen kandidat di disk privat dengan nama file acak. CV, KTP, dan
 * foto hanya satu per kandidat: unggahan baru menggantikan file lama, dan file
 * lama baru dihapus setelah data baru tersimpan.
 */
class StoreCandidateDocument
{
    public function __invoke(CandidateProfile $profile, CandidateDocumentType $type, UploadedFile $file, ?string $label): CandidateDocument
    {
        if (! $type->isSingle() && $profile->documents()->where('type', $type)->count() >= CandidateDocumentType::MAX_CERTIFICATES) {
            throw ValidationException::withMessages(['file' => __('Maksimal :count sertifikat. Hapus salah satu sebelum mengunggah yang baru.', ['count' => CandidateDocumentType::MAX_CERTIFICATES])]);
        }

        $extension = $file->guessExtension() ?? $file->extension();
        $path = (string) $file->storeAs(
            'candidates/'.$profile->public_id,
            Str::ulid()->toString().'.'.$extension,
            CandidateDocument::DISK,
        );

        try {
            [$document, $replaced] = DB::transaction(function () use ($profile, $type, $file, $label, $path) {
                $replaced = $type->isSingle()
                    ? $profile->documents()->where('type', $type)->get()
                    : collect();

                $replaced->each(fn (CandidateDocument $old) => $old->delete());

                $document = $profile->documents()->create([
                    'type' => $type,
                    'label' => $type->isSingle() ? null : $label,
                    'file_path' => $path,
                    'file_original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'file_mime' => (string) $file->getMimeType(),
                    'file_size' => (int) $file->getSize(),
                ]);

                return [$document, $replaced];
            });
        } catch (Throwable $exception) {
            Storage::disk(CandidateDocument::DISK)->delete($path);

            throw $exception;
        }

        foreach ($replaced as $old) {
            Storage::disk(CandidateDocument::DISK)->delete($old->file_path);
        }

        return $document;
    }
}

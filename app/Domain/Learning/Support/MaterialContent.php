<?php

namespace App\Domain\Learning\Support;

use App\Domain\Learning\Enums\MaterialType;
use App\Domain\Learning\Models\LearningMaterial;
use Illuminate\Support\Facades\URL;

/**
 * Isi materi yang aman dikirim ke browser. File privat hanya diakses lewat URL
 * bertanda tangan yang berlaku 10 menit, dan controller file tetap memeriksa Policy.
 */
class MaterialContent
{
    public const SIGNED_URL_MINUTES = 10;

    /**
     * @return array{body_html: string|null, file_url: string|null, file_name: string|null, file_mime: string|null, file_size: int|null, embed_url: string|null, external_url: string|null}
     */
    public static function for(LearningMaterial $material): array
    {
        return [
            'body_html' => $material->type === MaterialType::Article ? $material->body_html : null,
            'file_url' => $material->type->usesFile() && $material->file_path !== null ? self::signedFileUrl($material) : null,
            'file_name' => $material->file_original_name,
            'file_mime' => $material->file_mime,
            'file_size' => $material->file_size,
            'embed_url' => $material->type === MaterialType::VideoEmbed ? VideoEmbed::parse($material->external_url)?->embedUrl() : null,
            'external_url' => $material->type === MaterialType::Link ? $material->external_url : null,
        ];
    }

    public static function signedFileUrl(LearningMaterial $material): string
    {
        return URL::temporarySignedRoute(
            'materials.file',
            now()->addMinutes(self::SIGNED_URL_MINUTES),
            ['material' => $material],
        );
    }
}

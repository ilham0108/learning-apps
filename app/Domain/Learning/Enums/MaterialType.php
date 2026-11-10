<?php

namespace App\Domain\Learning\Enums;

enum MaterialType: string
{
    case Article = 'article';
    case Pdf = 'pdf';
    case Image = 'image';
    case VideoEmbed = 'video_embed';
    case VideoFile = 'video_file';
    case Link = 'link';

    public function usesFile(): bool
    {
        return in_array($this, [self::Pdf, self::Image, self::VideoFile], true);
    }

    /**
     * Aturan validasi file per jenis (rules Bagian 6): tipe MIME sebenarnya,
     * ekstensi, dan ukuran maksimal dalam kilobyte.
     *
     * @return list<string>
     */
    public function fileRules(): array
    {
        return match ($this) {
            self::Pdf => ['file', 'mimetypes:application/pdf', 'extensions:pdf', 'max:20480'],
            self::Image => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:5120'],
            self::VideoFile => ['file', 'mimetypes:video/mp4', 'extensions:mp4', 'max:204800'],
            default => ['prohibited'],
        };
    }
}

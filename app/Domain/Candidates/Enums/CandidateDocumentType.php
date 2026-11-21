<?php

namespace App\Domain\Candidates\Enums;

/**
 * Jenis dokumen kandidat. CV, KTP, dan foto profil masing-masing satu file
 * (unggahan baru menggantikan yang lama); sertifikat boleh lebih dari satu.
 */
enum CandidateDocumentType: string
{
    case Cv = 'cv';
    case IdCard = 'ktp';
    case Photo = 'photo';
    case Certificate = 'certificate';

    public const MAX_CERTIFICATES = 5;

    public function isSingle(): bool
    {
        return $this !== self::Certificate;
    }

    /**
     * Dokumen yang memuat data identitas; setiap kali HR membukanya dicatat di audit log.
     */
    public function isSensitive(): bool
    {
        return $this === self::IdCard;
    }

    /**
     * Validasi file: tipe MIME dibaca dari isi file, ditambah ekstensi dan ukuran (maks. 5 MB).
     *
     * @return array<int, string>
     */
    public function fileRules(): array
    {
        return match ($this) {
            self::Cv => ['file', 'mimetypes:application/pdf', 'extensions:pdf', 'max:5120'],
            self::Photo => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:5120'],
            self::IdCard, self::Certificate => ['file', 'mimetypes:application/pdf,image/jpeg,image/png', 'extensions:pdf,jpg,jpeg,png', 'max:5120'],
        };
    }
}

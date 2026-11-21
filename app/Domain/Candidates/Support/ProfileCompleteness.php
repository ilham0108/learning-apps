<?php

namespace App\Domain\Candidates\Support;

use App\Domain\Candidates\Enums\CandidateDocumentType;
use App\Domain\Candidates\Models\CandidateProfile;

/**
 * Aturan kelengkapan data diri. Profil harus lengkap sebelum kandidat dapat
 * memulai ujian. Pengalaman kerja, tautan, dan sertifikat bersifat opsional
 * (lulusan baru belum tentu punya pengalaman).
 */
class ProfileCompleteness
{
    public const ITEMS = ['personal', 'education', 'cv', 'id_card', 'photo'];

    /**
     * @return array{complete: bool, percent: int, items: array<int, array{key: string, done: bool}>}
     */
    public static function check(CandidateProfile $profile): array
    {
        $profile->loadMissing('user');
        $documents = $profile->documents()->pluck('type')->map(fn ($type) => $type instanceof CandidateDocumentType ? $type : CandidateDocumentType::from($type));
        $has = fn (CandidateDocumentType $type) => $documents->contains($type);

        $done = [
            'personal' => filled($profile->user->name)
                && filled($profile->phone)
                && filled($profile->city)
                && $profile->birth_date !== null
                && $profile->religion !== null
                && $profile->marital_status !== null,
            'education' => $profile->educations()->exists(),
            'cv' => $has(CandidateDocumentType::Cv),
            'id_card' => $has(CandidateDocumentType::IdCard),
            'photo' => $has(CandidateDocumentType::Photo),
        ];

        $completed = count(array_filter($done));

        return [
            'complete' => $completed === count($done),
            'percent' => (int) floor($completed / count($done) * 100),
            'items' => array_map(fn (string $key) => ['key' => $key, 'done' => $done[$key]], self::ITEMS),
        ];
    }

    public static function isComplete(CandidateProfile $profile): bool
    {
        return self::check($profile)['complete'];
    }
}

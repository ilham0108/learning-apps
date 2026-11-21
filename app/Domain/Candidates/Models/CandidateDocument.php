<?php

namespace App\Domain\Candidates\Models;

use App\Domain\Candidates\Enums\CandidateDocumentType;
use App\Domain\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dokumen kandidat di disk privat. File hanya disajikan lewat URL bertanda
 * tangan setelah Policy mengizinkan.
 *
 * @property int $id
 * @property string $public_id
 * @property int $candidate_profile_id
 * @property CandidateDocumentType $type
 * @property string|null $label
 * @property string $file_path
 * @property string $file_original_name
 * @property string $file_mime
 * @property int $file_size
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Hidden(['file_path'])]
#[Fillable(['candidate_profile_id', 'type', 'label', 'file_path', 'file_original_name', 'file_mime', 'file_size'])]
class CandidateDocument extends Model
{
    use HasPublicId;

    public const DISK = 'local';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CandidateDocumentType::class,
            'file_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CandidateProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_profile_id');
    }
}

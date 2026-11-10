<?php

namespace App\Domain\Candidates\Models;

use App\Domain\Shared\Concerns\HasPublicId;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\InternalNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan internal HR. Tidak pernah dikirim ke halaman kandidat.
 *
 * @property int $id
 * @property string $public_id
 * @property int $recruitment_application_id
 * @property int|null $author_id
 * @property string $body
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(InternalNoteFactory::class)]
#[Fillable(['recruitment_application_id', 'author_id', 'body'])]
class InternalNote extends Model
{
    /** @use HasFactory<InternalNoteFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return BelongsTo<RecruitmentApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

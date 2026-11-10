<?php

namespace App\Domain\Shared\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Memberi model kolom `public_id` (ULID) untuk dipakai di URL, props, dan JSON.
 * Primary key auto-increment tetap dipakai secara internal dan tidak pernah dikirim ke frontend.
 */
trait HasPublicId
{
    use HasUlids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}

<?php

namespace App\Domain\Shared\Audit;

use App\Domain\Shared\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Menulis audit log untuk tindakan administratif penting
 * (ubah status, ubah nilai, ekspor, ubah hak akses, hapus data).
 */
class ActivityLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?string $reason = null,
        ?User $actor = null,
    ): ActivityLog {
        $actor ??= Auth::user();

        return ActivityLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'reason' => $reason,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}

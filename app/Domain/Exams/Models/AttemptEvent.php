<?php

namespace App\Domain\Exams\Models;

use App\Domain\Exams\Enums\AttemptEventType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log peristiwa selama ujian. Bahan pertimbangan HR, bukan bukti kecurangan otomatis.
 *
 * @property int $id
 * @property int $exam_attempt_id
 * @property AttemptEventType $type
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<array-key, mixed>|null $meta
 * @property CarbonImmutable $occurred_at
 */
#[WithoutTimestamps]
#[Fillable(['exam_attempt_id', 'type', 'ip_address', 'user_agent', 'meta', 'occurred_at'])]
class AttemptEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AttemptEventType::class,
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }
}

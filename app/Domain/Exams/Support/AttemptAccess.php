<?php

namespace App\Domain\Exams\Support;

use App\Domain\Exams\Actions\SubmitAttempt;
use App\Domain\Exams\Enums\AttemptEventType;
use App\Domain\Exams\Exceptions\AttemptRejected;
use App\Domain\Exams\Models\ExamAttempt;
use Illuminate\Support\Str;

/**
 * Pemeriksaan yang dijalankan di setiap akses ke attempt:
 *
 * - Cek kedaluwarsa saat akses: attempt yang melewati deadline langsung
 *   di-auto-submit, sebagai cadangan jika scheduler tidak berjalan (risiko R11).
 * - Satu sesi aktif per attempt (aturan B12): membuka runner menerbitkan token
 *   baru; permintaan dengan token lama ditolak dan dicatat.
 */
class AttemptAccess
{
    public const SESSION_HEADER = 'X-Attempt-Session';

    public function __construct(
        private readonly SubmitAttempt $submit,
    ) {}

    /**
     * @return ExamAttempt Attempt terbaru (sudah dikumpulkan jika melewati deadline).
     */
    public function closeIfExpired(ExamAttempt $attempt): ExamAttempt
    {
        return $attempt->isPastDeadline() ? ($this->submit)($attempt, auto: true) : $attempt;
    }

    /**
     * Menerbitkan token sesi baru untuk runner; token lama otomatis tidak berlaku.
     */
    public function openSession(ExamAttempt $attempt): string
    {
        $token = Str::random(40);

        if ($attempt->session_token_hash !== null) {
            $this->event($attempt, AttemptEventType::Resumed);
        }

        $attempt->forceFill(['session_token_hash' => hash('sha256', $token)])->save();

        return $token;
    }

    /**
     * Memastikan attempt masih berjalan dan permintaan berasal dari sesi aktif.
     *
     * @throws AttemptRejected
     */
    public function ensureActive(ExamAttempt $attempt): ExamAttempt
    {
        $attempt = $this->closeIfExpired($attempt);

        if (! $attempt->isInProgress()) {
            throw AttemptRejected::finished();
        }

        $token = (string) request()->header(self::SESSION_HEADER, '');

        if ($attempt->session_token_hash === null || ! hash_equals($attempt->session_token_hash, hash('sha256', $token))) {
            $this->event($attempt, AttemptEventType::SessionReplaced);

            throw AttemptRejected::sessionReplaced();
        }

        return $attempt;
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function event(ExamAttempt $attempt, AttemptEventType $type, ?array $meta = null): void
    {
        $attempt->events()->create([
            'type' => $type,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta' => $meta,
            'occurred_at' => now(),
        ]);
    }
}

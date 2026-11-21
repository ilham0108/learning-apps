<?php

namespace App\Domain\Exams\Exceptions;

use RuntimeException;

/**
 * Permintaan pada attempt ditolak karena aturan ujian (waktu habis, sesi diganti,
 * soal sudah dilewati, dan sebagainya). `reason` dikirim ke klien sebagai kode
 * agar runner dapat menampilkan pesan yang sesuai.
 */
class AttemptRejected extends RuntimeException
{
    public const FINISHED = 'finished';

    public const SESSION_REPLACED = 'session_replaced';

    public const QUESTION_LOCKED = 'question_locked';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function finished(): self
    {
        return new self(self::FINISHED, __('Waktu ujian sudah habis atau ujian sudah dikumpulkan.'));
    }

    public static function sessionReplaced(): self
    {
        return new self(self::SESSION_REPLACED, __('Ujian ini dibuka di tab atau perangkat lain. Lanjutkan di sana, atau muat ulang halaman ini untuk memindahkan ujian ke sini.'));
    }

    public static function questionLocked(): self
    {
        return new self(self::QUESTION_LOCKED, __('Soal yang sudah dilewati tidak dapat diubah pada ujian ini.'));
    }
}

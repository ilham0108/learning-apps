<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Konversi waktu input form (zona tampilan, Asia/Jakarta) ke UTC untuk disimpan,
 * dan sebaliknya untuk mengisi ulang form (risiko R19).
 */
class LocalDateTime
{
    public const INPUT_FORMAT = 'Y-m-d\TH:i';

    public static function toUtc(?string $local): ?CarbonImmutable
    {
        if ($local === null || $local === '') {
            return null;
        }

        return CarbonImmutable::createFromFormat(self::INPUT_FORMAT, $local, self::zone())?->utc();
    }

    public static function toInput(?CarbonInterface $utc): ?string
    {
        return $utc?->copy()->setTimezone(self::zone())->format(self::INPUT_FORMAT);
    }

    private static function zone(): string
    {
        return (string) config('recruitment.display_timezone');
    }
}

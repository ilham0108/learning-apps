<?php

namespace App\Domain\Shared\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property array<array-key, mixed> $value
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Table(key: 'key', keyType: 'string', incrementing: false)]
#[Fillable(['key', 'value'])]
class SystemSetting extends Model
{
    public const CONSENT_POLICY_VERSION = 'consent_policy_version';

    public const RETENTION_MONTHS = 'retention_months';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public static function valueOf(string $key, mixed $default = null): mixed
    {
        return static::query()->find($key)->value ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}

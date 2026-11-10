<?php

namespace App\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Memvalidasi bahwa `public_id` dari frontend merujuk data yang ada di dalam
 * query yang diberikan, biasanya query yang sudah dibatasi `visibleTo($user)`.
 * Data di luar lingkup ditolak dengan pesan yang sama seperti data yang tidak ada.
 *
 * @template TModel of Model
 */
class PublicIdIn implements ValidationRule
{
    /**
     * @param  Builder<TModel>  $query
     */
    public function __construct(private readonly Builder $query) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Str::isUlid($value)) {
            $fail('validation.exists')->translate();

            return;
        }

        if (! (clone $this->query)->where('public_id', $value)->exists()) {
            $fail('validation.exists')->translate();
        }
    }
}

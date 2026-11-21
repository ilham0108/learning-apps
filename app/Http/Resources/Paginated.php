<?php

namespace App\Http\Resources;

use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Mengubah paginator menjadi props Inertia: `data` (lewat API Resource)
 * dan `meta` untuk komponen pagination. Tidak menyertakan URL atau ID internal.
 */
class Paginated
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  class-string<JsonResource>|Closure(mixed): array<string, mixed>  $resource  Resource atau fungsi pembentuk baris.
     * @return array{data: array<int, mixed>, meta: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}}
     */
    public static function make(LengthAwarePaginator $paginator, string|Closure $resource): array
    {
        return [
            'data' => $resource instanceof Closure
                ? $paginator->getCollection()->map($resource)->values()->all()
                : $resource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}

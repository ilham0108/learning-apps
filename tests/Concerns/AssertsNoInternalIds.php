<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan props Inertia dan respons JSON tidak memuat ID internal:
 * kunci `id` bernilai angka atau kunci `*_id` (foreign key) apa pun.
 */
trait AssertsNoInternalIds
{
    /**
     * @param  TestResponse<Response>  $response
     */
    protected function assertResponseHasNoInternalIds(TestResponse $response): void
    {
        $payload = $response->headers->get('Content-Type') === 'application/json'
            ? $response->json()
            : $response->inertiaProps();

        $this->assertNoInternalIds($payload);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    protected function assertNoInternalIds(array $data, string $path = ''): void
    {
        foreach ($data as $key => $value) {
            $currentPath = ltrim("{$path}.{$key}", '.');

            if (is_string($key)) {
                $this->assertFalse(
                    $key === 'id' && is_int($value),
                    "ID internal numerik ditemukan di '{$currentPath}'.",
                );
                $this->assertFalse(
                    str_ends_with($key, '_id') && $key !== 'public_id',
                    "Foreign key internal ditemukan di '{$currentPath}'.",
                );
            }

            if (is_array($value)) {
                $this->assertNoInternalIds($value, $currentPath);
            }
        }
    }
}

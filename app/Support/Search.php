<?php

namespace App\Support;

class Search
{
    /**
     * Pola LIKE "mengandung" dengan karakter wildcard dari pengguna di-escape.
     */
    public static function contains(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term)).'%';
    }
}

<?php

namespace App\Http\Requests\Admin;

/**
 * Aturan data kandidat yang dipakai bersama saat menambah dan mengubah kandidat.
 * Hanya data yang dibutuhkan untuk seleksi (UU PDP).
 */
class CandidateProfileRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 \-]{8,20}$/'],
            'last_education' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'phone' => 'nomor telepon',
            'last_education' => 'pendidikan terakhir',
        ];
    }
}

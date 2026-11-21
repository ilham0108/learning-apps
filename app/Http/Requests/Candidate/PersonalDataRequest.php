<?php

namespace App\Http\Requests\Candidate;

use App\Domain\Candidates\Enums\MaritalStatus;
use App\Domain\Candidates\Enums\Religion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Data diri yang diisi kandidat sendiri. Email tidak dapat diubah kandidat
 * (dikelola HR) agar akun tetap terhubung dengan lamarannya.
 */
class PersonalDataRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 \-]{8,20}$/'],
            'city' => ['required', 'string', 'max:100'],
            // Usia kerja minimal 15 tahun; lebih dari 100 tahun dianggap salah ketik.
            'birth_date' => ['required', 'date', 'before_or_equal:'.now()->subYears(15)->toDateString(), 'after:'.now()->subYears(100)->toDateString()],
            'religion' => ['required', Rule::enum(Religion::class)],
            'marital_status' => ['required', Rule::enum(MaritalStatus::class)],
            'linkedin_url' => ['nullable', 'string', 'max:255', 'url:https'],
            'portfolio_url' => ['nullable', 'string', 'max:255', 'url:http,https'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'phone' => 'nomor telepon',
            'city' => 'kota domisili',
            'birth_date' => 'tanggal lahir',
            'religion' => 'agama',
            'marital_status' => 'status pernikahan',
            'linkedin_url' => 'tautan LinkedIn',
            'portfolio_url' => 'tautan portofolio',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birth_date.before_or_equal' => __('Usia minimal 15 tahun.'),
            'birth_date.after' => __('Tanggal lahir tidak valid.'),
            'phone.regex' => __('Nomor telepon hanya boleh berisi angka, spasi, dan tanda hubung, boleh diawali +.'),
        ];
    }
}

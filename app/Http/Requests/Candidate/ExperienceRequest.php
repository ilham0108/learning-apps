<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Periode kerja diisi per bulan (format YYYY-MM) dan disimpan sebagai tanggal 1.
 */
class ExperienceRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company' => ['required', 'string', 'max:150'],
            'job_title' => ['required', 'string', 'max:150'],
            'started_on' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
            // Kosong berarti masih bekerja di sini.
            'ended_on' => ['nullable', 'date_format:Y-m', 'after_or_equal:started_on', 'before_or_equal:'.now()->format('Y-m')],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{company: string, job_title: string, started_on: string, ended_on: string|null, description: string|null}
     */
    public function experienceData(): array
    {
        $ended = $this->validated('ended_on');

        return [
            'company' => $this->validated('company'),
            'job_title' => $this->validated('job_title'),
            'started_on' => $this->validated('started_on').'-01',
            'ended_on' => $ended === null ? null : $ended.'-01',
            'description' => $this->validated('description'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company' => 'nama perusahaan',
            'job_title' => 'jabatan',
            'started_on' => 'bulan mulai',
            'ended_on' => 'bulan selesai',
            'description' => 'ringkasan tugas',
        ];
    }
}

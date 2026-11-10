<?php

namespace App\Http\Requests\Admin;

use App\Support\LocalDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Jadwal khusus individu (misalnya perpanjangan) yang menimpa jadwal ujian (aturan B4–B5).
 */
class ExamAssignmentScheduleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $dateTime = 'date_format:'.LocalDateTime::INPUT_FORMAT;

        return [
            'opens_at_override' => ['nullable', $dateTime],
            'closes_at_override' => ['nullable', $dateTime, 'after:opens_at_override'],
            'max_attempts_override' => ['nullable', 'integer', 'between:1,10'],
        ];
    }

    /**
     * @return array{opens_at_override: CarbonImmutable|null, closes_at_override: CarbonImmutable|null, max_attempts_override: int|null}
     */
    public function scheduleData(): array
    {
        return [
            'opens_at_override' => LocalDateTime::toUtc($this->input('opens_at_override')),
            'closes_at_override' => LocalDateTime::toUtc($this->input('closes_at_override')),
            'max_attempts_override' => $this->filled('max_attempts_override') ? $this->integer('max_attempts_override') : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'opens_at_override' => 'waktu buka khusus',
            'closes_at_override' => 'waktu tutup khusus',
            'max_attempts_override' => 'jumlah percobaan khusus',
        ];
    }
}

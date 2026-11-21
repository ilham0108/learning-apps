<?php

namespace App\Domain\QuestionBank\Import;

use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Membaca sheet "Soal" (atau sheet pertama) dari file .xlsx menjadi baris
 * berkunci nama kolom. Baris kosong dilewati; nomor baris mengikuti Excel.
 */
class QuestionSheetReader
{
    /**
     * @return array<int, array<string, string>> nomor baris => [kolom => nilai]
     */
    public function read(string $path): array
    {
        $reader = new Reader;

        try {
            $reader->open($path);
        } catch (IOException) {
            throw ValidationException::withMessages(['file' => __('File tidak dapat dibaca. Pastikan formatnya .xlsx.')]);
        }

        try {
            return $this->readSheet($reader);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('File tidak dapat dibaca. Pastikan formatnya .xlsx.')]);
        } finally {
            $reader->close();
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readSheet(Reader $reader): array
    {
        $sheet = null;

        foreach ($reader->getSheetIterator() as $candidate) {
            $sheet ??= $candidate;

            if (QuestionSheet::key($candidate->getName()) === QuestionSheet::key(QuestionSheet::SHEET_NAME)) {
                $sheet = $candidate;
                break;
            }
        }

        if ($sheet === null) {
            throw ValidationException::withMessages(['file' => __('File tidak berisi sheet.')]);
        }

        $headers = null;
        $rows = [];

        foreach ($sheet->getRowIterator() as $number => $row) {
            $values = array_map(fn ($value) => $this->stringify($value), $row->toArray());

            if (implode('', $values) === '') {
                continue;
            }

            if ($headers === null) {
                $headers = array_values(array_map(fn (string $header) => QuestionSheet::key($header), $values));
                $this->ensureRequiredColumns($headers);

                continue;
            }

            $record = [];

            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $record[$header] = $values[$index] ?? '';
                }
            }

            $rows[$number] = $record;

            if (count($rows) > QuestionSheet::MAX_ROWS) {
                throw ValidationException::withMessages(['file' => __('Maksimal :max baris soal per file.', ['max' => QuestionSheet::MAX_ROWS])]);
            }
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => __('File tidak berisi baris soal.')]);
        }

        return $rows;
    }

    /**
     * @param  list<string>  $headers
     */
    private function ensureRequiredColumns(array $headers): void
    {
        $missing = array_diff(['kategori', 'jenis', 'kesulitan', 'pertanyaan'], $headers);

        if ($missing !== []) {
            throw ValidationException::withMessages(['file' => __('Kolom wajib tidak ditemukan: :columns. Gunakan template yang disediakan.', [
                'columns' => implode(', ', $missing),
            ])]);
        }
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'benar' : 'salah',
            is_float($value) => rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.'),
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_scalar($value) => trim((string) $value),
            default => '',
        };
    }
}

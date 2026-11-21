<?php

namespace App\Domain\Reporting\Support;

/**
 * Menulis CSV (RFC 4180, pemisah koma) dengan BOM UTF-8 agar huruf
 * non-ASCII terbaca benar di Excel.
 *
 * Sel yang diawali =, +, -, @, tab, atau carriage return diberi awalan
 * apostrof agar tidak dieksekusi sebagai rumus saat dibuka di spreadsheet
 * (CSV/formula injection). Angka negatif tidak terdampak karena laporan tidak
 * menghasilkan nilai negatif.
 */
class CsvWriter
{
    /** @var resource */
    private $handle;

    public function __construct(string $path)
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Tidak dapat menulis file ekspor: {$path}");
        }

        $this->handle = $handle;
        fwrite($this->handle, "\xEF\xBB\xBF");
    }

    /**
     * @param  array<int|string, string|int|float|null>  $values
     */
    public function row(array $values): void
    {
        fputcsv($this->handle, array_map(self::safe(...), array_values($values)), ',', '"', '');
    }

    public function close(): void
    {
        fclose($this->handle);
    }

    public static function safe(string|int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = (string) $value;

        return is_string($value) && $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$text
            : $text;
    }
}

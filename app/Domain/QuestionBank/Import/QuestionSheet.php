<?php

namespace App\Domain\QuestionBank\Import;

use App\Domain\QuestionBank\Enums\Difficulty;
use App\Domain\QuestionBank\Enums\QuestionType;

/**
 * Format kolom file Excel bank soal. Dipakai bersama oleh template dan pembaca
 * impor agar keduanya selalu sepakat soal nama kolom dan nilai yang diterima.
 */
final class QuestionSheet
{
    public const SHEET_NAME = 'Soal';

    /** Jumlah kolom pilihan jawaban di template (opsi_a … opsi_e). */
    public const TEMPLATE_OPTIONS = 5;

    /** Batas pilihan jawaban yang dibaca (opsi_a … opsi_j), sama dengan form. */
    public const MAX_OPTIONS = 10;

    public const MAX_ROWS = 1000;

    /**
     * Kolom utama beserta keterangan untuk sheet petunjuk. Kolom opsi_* disisipkan setelah bobot.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $options = [];

        foreach (range(0, self::TEMPLATE_OPTIONS - 1) as $index) {
            $options[self::optionColumn($index)] = __('Teks pilihan :letter. Untuk pg dan pg_kompleks isi minimal dua pilihan.', ['letter' => self::letter($index)]);
        }

        return [
            'kode' => __('Wajib untuk baris studi_kasus: kode unik di file ini, misalnya K1. Boleh dikosongkan untuk soal lain.'),
            'induk' => __('Khusus sub-soal studi kasus: isi dengan kode studi kasusnya. Kategori dan topik sub-soal mengikuti induknya.'),
            'kategori' => __('Nama kategori yang sudah ada di bank soal (lihat sheet Kategori).'),
            'topik' => __('Opsional, maksimal 150 karakter.'),
            'jenis' => __('pg, pg_kompleks, benar_salah, isian, esai, atau studi_kasus.'),
            'kesulitan' => __('mudah, sedang, atau sulit.'),
            'pertanyaan' => __('Teks pertanyaan (untuk studi_kasus: teks kasus). Baris kosong memisahkan paragraf.'),
            'bobot' => __('Angka 0,01–1000. Diabaikan untuk studi_kasus (bobotnya jumlah sub-soal).'),
            ...$options,
            'kunci' => __('pg: satu huruf (B). pg_kompleks: beberapa huruf dipisah koma (A,C). benar_salah: Benar atau Salah. isian: jawaban yang diterima dipisah tanda | (Jakarta|DKI Jakarta). Kosongkan untuk esai dan studi_kasus.'),
            'rubrik' => __('Opsional, khusus esai: panduan penilaian untuk HR.'),
            'penjelasan' => __('Opsional: pembahasan jawaban.'),
        ];
    }

    public static function optionColumn(int $index): string
    {
        return 'opsi_'.mb_strtolower(self::letter($index));
    }

    public static function letter(int $index): string
    {
        return chr(ord('A') + $index);
    }

    /**
     * @return array<string, QuestionType>
     */
    public static function typeAliases(): array
    {
        return [
            'pg' => QuestionType::MultipleChoice,
            'pilihan_ganda' => QuestionType::MultipleChoice,
            'pg_kompleks' => QuestionType::MultipleSelect,
            'pilihan_ganda_kompleks' => QuestionType::MultipleSelect,
            'benar_salah' => QuestionType::TrueFalse,
            'isian' => QuestionType::ShortAnswer,
            'isian_singkat' => QuestionType::ShortAnswer,
            'esai' => QuestionType::Essay,
            'essay' => QuestionType::Essay,
            'studi_kasus' => QuestionType::CaseStudy,
        ];
    }

    public static function type(string $value): ?QuestionType
    {
        $key = self::key($value);

        return self::typeAliases()[$key] ?? QuestionType::tryFrom($key);
    }

    public static function difficulty(string $value): ?Difficulty
    {
        $key = self::key($value);

        return match ($key) {
            'mudah' => Difficulty::Easy,
            'sedang' => Difficulty::Medium,
            'sulit', 'sukar' => Difficulty::Hard,
            default => Difficulty::tryFrom($key),
        };
    }

    /**
     * Normalisasi nama kolom/nilai: huruf kecil, spasi dan tanda hubung menjadi garis bawah.
     */
    public static function key(string $value): string
    {
        return (string) preg_replace('/[\s\-]+/u', '_', mb_strtolower(trim($value)));
    }
}

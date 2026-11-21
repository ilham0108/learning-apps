<?php

namespace App\Domain\QuestionBank\Import;

use App\Domain\QuestionBank\Models\QuestionCategory;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Template Excel impor bank soal: sheet Soal (dengan contoh tiap jenis),
 * Petunjuk (arti tiap kolom), dan Kategori (kategori yang tersedia).
 */
class QuestionTemplate
{
    public function write(string $path): void
    {
        $header = new Style(fontBold: true, fontColor: 'FFFFFF', backgroundColor: '1F4E79');
        $columns = array_keys(QuestionSheet::columns());
        $category = QuestionCategory::query()->orderBy('name')->value('name') ?? __('Umum');

        $writer = new Writer;
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName(QuestionSheet::SHEET_NAME);
        $sheet->setColumnWidth(10, 1, 2);
        $sheet->setColumnWidth(18, 3, 4, 5, 6);
        $sheet->setColumnWidth(50, 7);
        $sheet->setColumnWidthForRange(18, 9, max(9, count($columns)));
        $writer->addRow(Row::fromValuesWithStyle($columns, $header));

        foreach ($this->examples($category) as $example) {
            $writer->addRow(Row::fromValues(array_map(fn (string $column) => $example[$column] ?? '', $columns)));
        }

        $guide = $writer->addNewSheetAndMakeItCurrent();
        $guide->setName(__('Petunjuk'));
        $guide->setColumnWidth(18, 1);
        $guide->setColumnWidth(110, 2);
        $writer->addRow(Row::fromValuesWithStyle([__('Kolom'), __('Keterangan')], $header));

        foreach (QuestionSheet::columns() as $column => $description) {
            $writer->addRow(Row::fromValues([$column, $description]));
        }

        $writer->addRow(Row::fromValues([]));
        foreach ([
            __('Hapus baris contoh di sheet Soal sebelum mengimpor.'),
            __('Kolom opsi boleh ditambah sampai opsi_j (10 pilihan).'),
            __('Jika ada satu baris yang salah, tidak ada soal yang disimpan. Perbaiki baris yang dilaporkan lalu impor ulang.'),
        ] as $note) {
            $writer->addRow(Row::fromValues(['', $note]));
        }

        $categories = $writer->addNewSheetAndMakeItCurrent();
        $categories->setName(__('Kategori'));
        $categories->setColumnWidth(40, 1);
        $writer->addRow(Row::fromValuesWithStyle([__('Kategori tersedia')], $header));

        foreach (QuestionCategory::query()->orderBy('name')->pluck('name') as $name) {
            $writer->addRow(Row::fromValues([$name]));
        }

        $writer->setCurrentSheet($sheet);
        $writer->close();
    }

    /**
     * @return list<array<string, string|int|float>>
     */
    private function examples(string $category): array
    {
        return [
            [
                'kategori' => $category, 'topik' => __('Pengetahuan umum'), 'jenis' => 'pg', 'kesulitan' => 'mudah',
                'pertanyaan' => __('Ibu kota Indonesia adalah …'), 'bobot' => 10,
                'opsi_a' => 'Bandung', 'opsi_b' => 'Jakarta', 'opsi_c' => 'Surabaya', 'opsi_d' => 'Medan',
                'kunci' => 'B', 'penjelasan' => __('Jakarta adalah ibu kota Indonesia.'),
            ],
            [
                'kategori' => $category, 'jenis' => 'pg_kompleks', 'kesulitan' => 'sedang',
                'pertanyaan' => __('Manakah yang termasuk bilangan prima?'), 'bobot' => 10,
                'opsi_a' => '2', 'opsi_b' => '4', 'opsi_c' => '5', 'opsi_d' => '9', 'kunci' => 'A,C',
            ],
            [
                'kategori' => $category, 'jenis' => 'benar_salah', 'kesulitan' => 'mudah',
                'pertanyaan' => __('Matahari terbit dari timur.'), 'bobot' => 5, 'kunci' => 'Benar',
            ],
            [
                'kategori' => $category, 'jenis' => 'isian', 'kesulitan' => 'sedang',
                'pertanyaan' => __('Sebutkan ibu kota Jawa Barat.'), 'bobot' => 5, 'kunci' => 'Bandung|Kota Bandung',
            ],
            [
                'kategori' => $category, 'jenis' => 'esai', 'kesulitan' => 'sulit',
                'pertanyaan' => __('Jelaskan cara Anda menangani keluhan pelanggan.'), 'bobot' => 20,
                'rubrik' => __('Empati, solusi konkret, dan tindak lanjut.'),
            ],
            [
                'kode' => 'K1', 'kategori' => $category, 'topik' => __('Studi kasus'), 'jenis' => 'studi_kasus', 'kesulitan' => 'sedang',
                'pertanyaan' => __('Sebuah toko menjual 120 barang pada bulan Januari dan 150 barang pada bulan Februari.'),
            ],
            [
                'induk' => 'K1', 'jenis' => 'pg', 'kesulitan' => 'sedang',
                'pertanyaan' => __('Berapa kenaikan penjualan dari Januari ke Februari?'), 'bobot' => 5,
                'opsi_a' => '20', 'opsi_b' => '30', 'opsi_c' => '40', 'kunci' => 'B',
            ],
            [
                'induk' => 'K1', 'jenis' => 'esai', 'kesulitan' => 'sedang',
                'pertanyaan' => __('Apa yang mungkin menyebabkan kenaikan tersebut?'), 'bobot' => 10,
            ],
        ];
    }
}

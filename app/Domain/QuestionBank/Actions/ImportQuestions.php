<?php

namespace App\Domain\QuestionBank\Actions;

use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Import\QuestionSheet;
use App\Domain\QuestionBank\Models\QuestionCategory;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mengimpor soal dari baris Excel (hasil QuestionSheetReader).
 *
 * Impor bersifat semua-atau-tidak-sama-sekali: setiap soal disimpan lewat
 * SaveQuestion (aturan sama dengan form), dan satu baris yang salah membatalkan
 * seluruh impor. Kesalahan dilaporkan per nomor baris dengan kunci "rows.{n}".
 */
class ImportQuestions
{
    /** @var array<int, list<string>> */
    private array $errors = [];

    public function __construct(
        private readonly SaveQuestion $saveQuestion,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array<int, array<string, string>>  $rows  nomor baris => [kolom => nilai]
     * @return int jumlah soal (tingkat atas) yang diimpor
     */
    public function __invoke(array $rows, User $actor): int
    {
        $this->errors = [];
        $questions = $this->parse($rows);
        $this->throwIfErrors();

        return DB::transaction(function () use ($questions, $actor) {
            foreach ($questions as $question) {
                try {
                    ($this->saveQuestion)(null, $question['data'], $actor);
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $key => $messages) {
                        $row = preg_match('/^children\.(\d+)\./', $key, $match) === 1
                            ? $question['child_rows'][(int) $match[1]] ?? $question['row']
                            : $question['row'];

                        array_push($this->errors[$row], ...$messages);
                    }
                }
            }

            // Dilempar di dalam transaksi agar soal yang sudah tersimpan ikut dibatalkan.
            $this->throwIfErrors();

            $this->logger->log('question.imported', properties: ['count' => count($questions)], actor: $actor);

            return count($questions);
        });
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return list<array{row: int, data: array<string, mixed>, child_rows: list<int>}>
     */
    private function parse(array $rows): array
    {
        $categories = QuestionCategory::query()->pluck('id', 'name')
            ->mapWithKeys(fn (int $id, string $name) => [mb_strtolower(trim($name)) => $id])
            ->all();

        $questions = [];
        $cases = [];
        $children = [];

        foreach ($rows as $row => $values) {
            $this->errors[$row] = [];
            $parent = trim($values['induk'] ?? '');
            $item = $this->item($row, $values, isChild: $parent !== '');

            if ($parent !== '') {
                $children[QuestionSheet::key($parent)][] = ['row' => $row, 'item' => $item];

                continue;
            }

            $category = mb_strtolower(trim($values['kategori'] ?? ''));
            $topic = trim($values['topik'] ?? '');

            if ($category === '') {
                $this->errors[$row][] = $this->message('Kategori wajib diisi.');
            } elseif (! isset($categories[$category])) {
                $this->errors[$row][] = $this->message('Kategori ":name" tidak ditemukan di bank soal.', ['name' => trim($values['kategori'])]);
            }

            if (mb_strlen($topic) > 150) {
                $this->errors[$row][] = $this->message('Topik maksimal 150 karakter.');
            }

            $index = count($questions);
            $questions[] = [
                'row' => $row,
                'data' => [
                    'question_category_id' => $categories[$category] ?? 0,
                    'topic' => $topic !== '' ? $topic : null,
                    ...$item,
                    'children' => [],
                ],
                'child_rows' => [],
            ];

            if (($item['type'] ?? null) === QuestionType::CaseStudy) {
                $code = QuestionSheet::key($values['kode'] ?? '');

                if ($code === '') {
                    $this->errors[$row][] = $this->message('Studi kasus wajib memiliki kode agar sub-soal dapat merujuknya.');
                } elseif (isset($cases[$code])) {
                    $this->errors[$row][] = $this->message('Kode ":code" sudah dipakai studi kasus di baris :row.', ['code' => $values['kode'], 'row' => $questions[$cases[$code]]['row']]);
                } else {
                    $cases[$code] = $index;
                }
            }
        }

        foreach ($children as $code => $items) {
            if (! isset($cases[$code])) {
                foreach ($items as $child) {
                    $this->errors[$child['row']][] = $this->message('Induk ":code" tidak ditemukan. Isi dengan kode baris studi kasus.', ['code' => $rows[$child['row']]['induk']]);
                }

                continue;
            }

            $case = &$questions[$cases[$code]];

            if (count($items) > 20) {
                $this->errors[$case['row']][] = $this->message('Studi kasus maksimal memiliki 20 sub-soal.');
            }

            foreach ($items as $child) {
                $case['data']['children'][] = $child['item'];
                $case['child_rows'][] = $child['row'];
            }

            unset($case);
        }

        return $questions;
    }

    /**
     * Data satu soal dengan bentuk yang sama seperti QuestionRequest::itemData().
     *
     * @param  array<string, string>  $values
     * @return array<string, mixed>
     */
    private function item(int $row, array $values, bool $isChild): array
    {
        $errors = &$this->errors[$row];

        $type = QuestionSheet::type($values['jenis'] ?? '');
        $difficulty = QuestionSheet::difficulty($values['kesulitan'] ?? '');
        $body = trim($values['pertanyaan'] ?? '');

        if ($type === null) {
            $errors[] = trim($values['jenis'] ?? '') === ''
                ? $this->message('Jenis soal wajib diisi.')
                : $this->message('Jenis soal ":value" tidak dikenal. Gunakan pg, pg_kompleks, benar_salah, isian, esai, atau studi_kasus.', ['value' => $values['jenis']]);
        } elseif ($isChild && $type->isContainer()) {
            $errors[] = $this->message('Sub-soal tidak boleh berupa studi kasus.');
        }

        if ($difficulty === null) {
            $errors[] = trim($values['kesulitan'] ?? '') === ''
                ? $this->message('Tingkat kesulitan wajib diisi.')
                : $this->message('Tingkat kesulitan ":value" tidak dikenal. Gunakan mudah, sedang, atau sulit.', ['value' => $values['kesulitan']]);
        }

        if ($body === '') {
            $errors[] = $this->message('Pertanyaan wajib diisi.');
        } elseif (mb_strlen($body) > 50000) {
            $errors[] = $this->message('Pertanyaan terlalu panjang.');
        }

        $rubric = trim($values['rubrik'] ?? '');
        $explanation = trim($values['penjelasan'] ?? '');

        if (mb_strlen($rubric) > 5000) {
            $errors[] = $this->message('Rubrik maksimal 5000 karakter.');
        }

        if (mb_strlen($explanation) > 20000) {
            $errors[] = $this->message('Penjelasan terlalu panjang.');
        }

        if ($type === null || $difficulty === null) {
            return [];
        }

        return [
            'type' => $type,
            'difficulty' => $difficulty,
            'body_html' => $this->toHtml($body),
            'explanation_html' => $explanation !== '' ? $this->toHtml($explanation) : null,
            'rubric' => $rubric !== '' ? $rubric : null,
            'default_points' => $type->isContainer() ? '0.00' : $this->points($values['bobot'] ?? '', $errors),
            ...$this->answers($type, $values, $errors),
        ];
    }

    /**
     * @param  list<string>  $errors
     */
    private function points(string $value, array &$errors): string
    {
        $value = str_replace(',', '.', trim($value));

        if ($value === '') {
            $errors[] = $this->message('Bobot wajib diisi.');

            return '0.00';
        }

        if (! is_numeric($value) || (float) $value < 0.01 || (float) $value > 1000) {
            $errors[] = $this->message('Bobot harus berupa angka 0,01–1000.');

            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * Pilihan jawaban dan jawaban diterima dari kolom opsi_* dan kunci.
     *
     * @param  array<string, string>  $values
     * @param  list<string>  $errors
     * @return array{options: list<array{body: string, is_correct: bool}>, accepted_answers: list<string>}
     */
    private function answers(QuestionType $type, array $values, array &$errors): array
    {
        $key = trim($values['kunci'] ?? '');
        $result = ['options' => [], 'accepted_answers' => []];

        if ($type === QuestionType::TrueFalse) {
            $isTrue = match (QuestionSheet::key($key)) {
                'benar', 'b', 'true', 'ya', '1' => true,
                'salah', 's', 'false', 'tidak', '0' => false,
                default => null,
            };

            if ($isTrue === null) {
                $errors[] = $this->message('Kunci benar_salah harus "Benar" atau "Salah".');
            }

            // SaveQuestion membentuk ulang opsi Benar/Salah dari is_correct opsi pertama.
            $result['options'] = [
                ['body' => $this->message('Benar'), 'is_correct' => $isTrue ?? true],
                ['body' => $this->message('Salah'), 'is_correct' => ! ($isTrue ?? true)],
            ];

            return $result;
        }

        if ($type === QuestionType::ShortAnswer) {
            $answers = array_values(array_filter(array_map('trim', preg_split('/[|\r\n]+/u', $key) ?: [])));

            if ($answers === []) {
                $errors[] = $this->message('Kunci isian wajib diisi. Pisahkan beberapa jawaban dengan tanda |.');
            } elseif (count($answers) > 20) {
                $errors[] = $this->message('Maksimal 20 jawaban yang diterima.');
            } elseif (max(array_map('mb_strlen', $answers)) > 255) {
                $errors[] = $this->message('Setiap jawaban yang diterima maksimal 255 karakter.');
            }

            $result['accepted_answers'] = $answers;

            return $result;
        }

        if (! $type->hasOptions()) {
            return $result;
        }

        // Pilihan diurutkan menurut huruf kolom; kolom kosong dilewati.
        $options = [];

        foreach (range(0, QuestionSheet::MAX_OPTIONS - 1) as $index) {
            $text = trim($values[QuestionSheet::optionColumn($index)] ?? '');

            if ($text !== '') {
                $options[QuestionSheet::letter($index)] = $text;
            }
        }

        if (count($options) < 2) {
            $errors[] = $this->message('Isi minimal dua pilihan jawaban (kolom opsi_a, opsi_b, …).');
        }

        if (max([0, ...array_map('mb_strlen', $options)]) > 1000) {
            $errors[] = $this->message('Setiap pilihan jawaban maksimal 1000 karakter.');
        }

        $letters = array_values(array_unique(array_filter(
            preg_split('/[\s,;]+/u', mb_strtoupper($key)) ?: [],
            fn (string $letter) => $letter !== '',
        )));

        if ($letters === []) {
            $errors[] = $this->message('Kunci jawaban wajib diisi dengan huruf pilihan yang benar.');
        } elseif ($type === QuestionType::MultipleChoice && count($letters) > 1) {
            $errors[] = $this->message('Pilihan ganda hanya boleh memiliki satu kunci jawaban.');
        } elseif (($unknown = array_diff($letters, array_keys($options))) !== []) {
            $errors[] = $this->message('Kunci ":letters" tidak sesuai dengan pilihan yang diisi.', ['letters' => implode(', ', $unknown)]);
        }

        foreach ($options as $letter => $text) {
            $result['options'][] = ['body' => $text, 'is_correct' => in_array((string) $letter, $letters, true)];
        }

        return $result;
    }

    /**
     * Teks biasa menjadi HTML: baris kosong memisahkan paragraf, baris baru menjadi <br>.
     */
    private function toHtml(string $text): string
    {
        $paragraphs = preg_split('/\R\s*\R/u', str_replace("\r\n", "\n", $text)) ?: [];

        return implode('', array_map(
            fn (string $paragraph) => '<p>'.preg_replace('/\s*\n\s*/u', '<br>', e(trim($paragraph))).'</p>',
            array_filter($paragraphs, fn (string $paragraph) => trim($paragraph) !== ''),
        ));
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    private function message(string $key, array $replace = []): string
    {
        $message = __($key, $replace);

        return is_string($message) ? $message : $key;
    }

    private function throwIfErrors(): void
    {
        $messages = [];

        foreach ($this->errors as $row => $errors) {
            if ($errors !== []) {
                $messages["rows.{$row}"] = $this->message('Baris :row: :errors', ['row' => $row, 'errors' => implode(' ', array_unique($errors))]);
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }
}

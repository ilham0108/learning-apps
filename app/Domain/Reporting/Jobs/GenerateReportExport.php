<?php

namespace App\Domain\Reporting\Jobs;

use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Queries\ApplicationScope;
use App\Domain\Reporting\Reports\ReportRegistry;
use App\Domain\Reporting\Support\CsvWriter;
use App\Domain\Reporting\Support\ReportFilters;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Membuat file CSV di antrean. Laporan dijalankan dengan lingkup akses
 * peminta, sehingga isi file sama dengan yang boleh ia lihat di layar.
 * Data dibaca per 500 baris agar memori tetap kecil untuk laporan besar.
 */
class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public const DISK = 'local';

    public const EXPIRES_AFTER_DAYS = 7;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $exportId) {}

    public function handle(): void
    {
        /** @var ReportExport|null $export */
        $export = ReportExport::query()->with('requester')->find($this->exportId);

        if ($export === null || $export->status !== ExportStatus::Queued) {
            return;
        }

        $export->update(['status' => ExportStatus::Processing]);

        $report = ReportRegistry::for($export->report);
        $filters = ReportFilters::resolve($export->filters ?? [], $export->requester);
        $path = "exports/{$export->public_id}.csv";
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory('exports');

        try {
            $writer = new CsvWriter($disk->path($path));
            $writer->row(array_values($report->columns()));

            $report->query(ApplicationScope::ids($export->requester, $filters), $filters)
                ->lazy(500)
                ->each(fn (object $row) => $writer->row($report->row($row)));

            $writer->close();

            $export->update([
                'status' => ExportStatus::Ready,
                'file_path' => $path,
                'completed_at' => now(),
                'expires_at' => now()->addDays(self::EXPIRES_AFTER_DAYS),
            ]);
        } catch (Throwable $exception) {
            $disk->delete($path);
            Log::error('Ekspor laporan gagal', ['export' => $export->public_id, 'error' => $exception->getMessage()]);

            // Pesan teknis tidak ditampilkan ke pengguna.
            $export->update([
                'status' => ExportStatus::Failed,
                'error_message' => __('Ekspor gagal diproses. Coba lagi; jika tetap gagal, hubungi admin sistem.'),
                'completed_at' => now(),
            ]);
        }
    }
}

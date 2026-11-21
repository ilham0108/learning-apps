<?php

namespace App\Console\Commands;

use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Jobs\GenerateReportExport;
use App\Domain\Reporting\Models\ReportExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Menghapus file ekspor yang sudah lewat masa berlaku (7 hari) dan menandai
 * ekspornya kedaluwarsa. Data permintaan tetap ada untuk riwayat dan audit.
 */
class PruneReportExports extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Hapus file ekspor laporan yang sudah kedaluwarsa';

    public function handle(): int
    {
        $count = 0;

        ReportExport::query()
            ->where('status', ExportStatus::Ready)
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($exports) use (&$count): void {
                foreach ($exports as $export) {
                    if ($export->file_path !== null) {
                        Storage::disk(GenerateReportExport::DISK)->delete($export->file_path);
                    }

                    $export->update(['status' => ExportStatus::Expired, 'file_path' => null]);
                    $count++;
                }
            });

        $this->info("{$count} ekspor kedaluwarsa dihapus.");

        return self::SUCCESS;
    }
}

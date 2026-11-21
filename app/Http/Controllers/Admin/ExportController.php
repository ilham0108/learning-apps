<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reporting\Actions\RequestReportExport;
use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Jobs\GenerateReportExport;
use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Reports\ReportRegistry;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportFilterRequest;
use App\Http\Resources\Paginated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daftar ekspor milik pengguna, permintaan ekspor baru, dan unduhan file.
 * File hanya bisa diunduh oleh peminta dan berlaku 7 hari.
 */
class ExportController extends Controller
{
    public function index(Request $request): Response
    {
        $exports = ReportExport::query()
            ->where('requested_by', $request->user()->id)
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/exports/index', [
            'exports' => Paginated::make($exports, fn (ReportExport $export) => [
                'id' => $export->public_id,
                'report' => $export->report->value,
                'title' => ReportRegistry::for($export->report)->title(),
                'filters_count' => count($export->filters ?? []),
                'status' => $this->status($export),
                'error_message' => $export->error_message,
                'created_at' => $export->created_at?->toIso8601String(),
                'completed_at' => $export->completed_at?->toIso8601String(),
                'expires_at' => $export->expires_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(ReportFilterRequest $request, RequestReportExport $requestExport): RedirectResponse
    {
        $requestExport($request->user(), $request->reportType(), $request->filters());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ekspor sedang diproses. File muncul di daftar ini setelah siap.')]);

        return to_route('admin.exports.index');
    }

    public function download(Request $request, ReportExport $export, ActivityLogger $logger): StreamedResponse|RedirectResponse
    {
        $this->authorize('download', $export);

        $disk = Storage::disk(GenerateReportExport::DISK);

        if ($this->status($export) !== ExportStatus::Ready->value || $export->file_path === null || ! $disk->exists($export->file_path)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('File ekspor ini tidak tersedia lagi. Buat ekspor baru dari halaman Laporan.')]);

            return to_route('admin.exports.index');
        }

        $logger->log('export.downloaded', $export, ['report' => $export->report->value]);

        $filename = 'laporan-'.str_replace('_', '-', $export->report->value).'-'.$export->completed_at?->setTimezone((string) config('recruitment.display_timezone'))->format('Ymd-Hi').'.csv';

        return $disk->download($export->file_path, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Ekspor siap yang sudah lewat masa berlaku dianggap kedaluwarsa walaupun
     * job pembersihan harian belum berjalan.
     */
    private function status(ReportExport $export): string
    {
        return $export->status === ExportStatus::Ready && $export->expires_at !== null && $export->expires_at->isPast()
            ? ExportStatus::Expired->value
            : $export->status->value;
    }
}

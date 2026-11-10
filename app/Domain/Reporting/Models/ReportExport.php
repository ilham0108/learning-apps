<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Reporting\Enums\ExportFormat;
use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ReportExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ekspor laporan yang diproses lewat queue. File hanya dapat diunduh oleh peminta.
 *
 * @property int $id
 * @property string $public_id
 * @property int $requested_by
 * @property ReportType $report
 * @property ExportFormat $format
 * @property array<array-key, mixed>|null $filters
 * @property ExportStatus $status
 * @property string|null $file_path
 * @property string|null $error_message
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(ReportExportFactory::class)]
#[Fillable(['requested_by', 'report', 'format', 'filters', 'status', 'file_path', 'error_message', 'completed_at', 'expires_at'])]
class ReportExport extends Model
{
    /** @use HasFactory<ReportExportFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report' => ReportType::class,
            'format' => ExportFormat::class,
            'filters' => 'array',
            'status' => ExportStatus::class,
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}

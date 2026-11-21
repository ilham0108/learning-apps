<?php

namespace App\Domain\Reporting\Policies;

use App\Domain\Reporting\Models\ReportExport;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * File ekspor hanya dapat diunduh oleh peminta (Tahap 1, matriks akses),
 * termasuk Super Admin tidak dapat mengunduh ekspor milik orang lain.
 */
class ReportExportPolicy
{
    public function download(User $user, ReportExport $export): Response
    {
        return $export->requested_by === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}

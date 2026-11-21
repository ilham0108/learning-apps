<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\Enums\Permission;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Domain\Shared\Models\ActivityLog;
use App\Http\Controllers\Controller;
use App\Http\Resources\Paginated;
use App\Http\Rules\PublicIdIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audit log tindakan administratif penting. Hanya Super Admin (Tahap 1,
 * matriks akses); HR mendapat 404.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission(Permission::AuditView), 404);

        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', new PublicIdIn(User::query())],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $logs = ActivityLog::query()
            ->with(['actor', 'subject'])
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('action', $action))
            ->when($filters['actor'] ?? null, fn ($query, string $actor) => $query->whereIn('actor_id', User::query()->select('id')->where('public_id', $actor)))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('admin/audit-log/index', [
            'logs' => Paginated::make($logs, fn (ActivityLog $log) => [
                'action' => $log->action,
                'actor' => $log->actor === null ? null : [
                    'name' => $log->actor->name,
                    'role' => $log->actor->role->value,
                ],
                'subject' => self::subjectLabel($log->subject),
                'reason' => $log->reason,
                'properties' => $log->properties === null ? null : json_encode($log->properties, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
            'filters' => ['action' => $filters['action'] ?? null, 'actor' => $filters['actor'] ?? null],
            'options' => [
                'actions' => DB::table('activity_logs')->distinct()->orderBy('action')->pluck('action')->all(),
                'actors' => User::query()->whereIn('id', DB::table('activity_logs')->whereNotNull('actor_id')->distinct()->select('actor_id'))
                    ->orderBy('name')->get()
                    ->map(fn (User $user) => ['id' => $user->public_id, 'name' => $user->name])->all(),
            ],
        ]);
    }

    /**
     * Nama yang mudah dibaca untuk objek yang dicatat, tanpa ID internal.
     */
    private static function subjectLabel(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        $name = match (true) {
            $subject instanceof CandidateProfile => $subject->user?->name.' ('.$subject->candidate_number.')',
            $subject instanceof RecruitmentApplication => $subject->candidateProfile?->user?->name,
            default => $subject->getAttribute('name') ?? $subject->getAttribute('title'),
        };

        $type = self::SUBJECT_TYPES[$subject->getMorphClass()] ?? Str::headline($subject->getMorphClass());

        return $name ? __($type).': '.$name : __($type);
    }

    private const SUBJECT_TYPES = [
        'user' => 'Akun',
        'candidate_profile' => 'Kandidat',
        'recruitment_campaign' => 'Rekrutmen',
        'recruitment_application' => 'Lamaran',
        'internal_note' => 'Catatan internal',
        'candidate_evaluation' => 'Evaluasi',
        'learning_program' => 'Program',
        'learning_material' => 'Materi',
        'question' => 'Soal',
        'exam' => 'Ujian',
        'exam_assignment' => 'Penugasan ujian',
        'exam_attempt' => 'Pengerjaan ujian',
        'attempt_answer' => 'Jawaban',
        'report_export' => 'Ekspor laporan',
        'data_deletion_request' => 'Permintaan hapus data',
    ];
}

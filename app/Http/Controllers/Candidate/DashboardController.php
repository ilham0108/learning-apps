<?php

namespace App\Http\Controllers\Candidate;

use App\Domain\Candidates\Models\RecruitmentApplication;
use App\Http\Controllers\Controller;
use App\Http\Resources\Candidates\OwnApplicationResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda kandidat. `/dashboard` juga menjadi tujuan setelah login, sehingga
 * Super Admin dan HR diarahkan ke beranda administrasi.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isCandidate()) {
            return to_route('admin.dashboard');
        }

        $applications = RecruitmentApplication::query()
            ->visibleTo($user)
            ->with('campaign.position.department')
            ->latest('applied_at')
            ->get();

        return Inertia::render('candidate/dashboard', [
            'candidateNumber' => $user->candidateProfile?->candidate_number,
            'applications' => OwnApplicationResource::collection($applications)->resolve(),
        ]);
    }
}

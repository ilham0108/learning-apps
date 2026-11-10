<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Models\RecruitmentCampaign;
use App\Http\Controllers\Controller;
use App\Http\Resources\Candidates\CampaignSummaryResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda Super Admin dan HR. Statistik lengkap dibuat di Tahap 8;
 * untuk sekarang menampilkan gelombang dalam lingkup pengguna.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $campaigns = RecruitmentCampaign::query()
            ->visibleTo($user)
            ->with('position.department')
            ->withCount('applications')
            ->orderByDesc('starts_on')
            ->get();

        return Inertia::render('admin/dashboard', [
            'campaigns' => CampaignSummaryResource::collection($campaigns)->resolve(),
            'candidateCount' => CandidateProfile::query()->visibleTo($user)->count(),
        ]);
    }
}

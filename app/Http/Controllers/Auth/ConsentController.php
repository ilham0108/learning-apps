<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Access\Actions\RecordConsent;
use App\Domain\Access\Support\ConsentPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConsentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsentController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (! ConsentPolicy::needsConsent($request->user())) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/consent', [
            'policyVersion' => ConsentPolicy::currentVersion(),
            'previouslyAccepted' => $request->user()->candidateProfile?->consent_given_at !== null,
        ]);
    }

    public function store(ConsentRequest $request, RecordConsent $recordConsent): RedirectResponse
    {
        $recordConsent($request->user());

        return to_route('dashboard');
    }
}

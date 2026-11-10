<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Access\Actions\ActivateCandidateAccount;
use App\Domain\Access\Actions\CreateActivationLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ActivateAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ActivationController extends Controller
{
    public function show(User $user, string $token): Response
    {
        $this->ensureValidToken($user, $token);

        return Inertia::render('auth/activate', [
            'userId' => $user->public_id,
            'token' => $token,
            'name' => $user->name,
            'email' => $user->email,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(ActivateAccountRequest $request, User $user, string $token, ActivateCandidateAccount $activate): RedirectResponse
    {
        $this->ensureValidToken($user, $token);

        $activate($user, $request->string('password')->toString());

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('consent.show');
    }

    /**
     * Token tidak valid, kedaluwarsa, atau sudah dipakai diperlakukan sama: 404.
     */
    private function ensureValidToken(User $user, string $token): void
    {
        abort_unless(
            $user->isCandidate()
                && $user->is_active
                && $user->candidateProfile?->activated_at === null
                && CreateActivationLink::broker()->tokenExists($user, $token),
            404,
        );
    }
}

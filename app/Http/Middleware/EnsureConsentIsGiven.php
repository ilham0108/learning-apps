<?php

namespace App\Http\Middleware;

use App\Domain\Access\Support\ConsentPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kandidat harus menyetujui kebijakan pemrosesan data (versi terbaru) sebelum memakai aplikasi.
 */
class EnsureConsentIsGiven
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null && ConsentPolicy::needsConsent($request->user())) {
            return redirect()->route('consent.show');
        }

        return $next($request);
    }
}

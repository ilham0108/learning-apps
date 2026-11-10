<?php

namespace App\Http\Middleware;

use App\Domain\Shared\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi route ke role tertentu, misalnya `role:super_admin,hr`.
 * Role lain mendapat 404 agar keberadaan halaman tidak terungkap.
 */
class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $role) => Role::from($role), $roles);

        abort_unless(in_array($request->user()?->role, $allowed, true), 404);

        return $next($request);
    }
}

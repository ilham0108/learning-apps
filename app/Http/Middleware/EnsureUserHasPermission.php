<?php

namespace App\Http\Middleware;

use App\Domain\Access\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi route untuk pengguna yang memiliki salah satu hak yang disebut,
 * misalnya `permission:reports.view`. Pengguna tanpa hak mendapat 404 agar
 * halaman yang tidak boleh diakses tidak terlihat ada.
 */
class EnsureUserHasPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        $allowed = $user !== null && collect($permissions)
            ->contains(fn (string $permission) => $user->hasPermission(Permission::from($permission)));

        abort_unless($allowed, 404);

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Izinkan hanya user dengan salah satu role yang disebut. Contoh: `role:admin,operator`.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user && $user->is_active && in_array($user->role->value, $roles, true), 403);

        return $next($request);
    }
}

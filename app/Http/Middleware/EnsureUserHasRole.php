<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $roleValue = $user?->role instanceof \BackedEnum ? $user->role->value : $user?->role;

        if (! $user) {
            abort(403, __('messages.forbidden'));
        }

        if (! in_array($roleValue, $roles, true)) {
            abort(403, __('messages.forbidden'));
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based access control. Usage on a route: ->middleware('role:admin').
 * Multiple roles may be passed: ->middleware('role:admin,consumer').
 */
class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors'  => null,
            ], 401);
        }

        if (! in_array($user->role->value, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'This action is unauthorized for your role.',
                'errors'  => null,
            ], 403);
        }

        return $next($request);
    }
}

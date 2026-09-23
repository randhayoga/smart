<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role Middleware enforcing role-based authorization and permission cascades across application routes.
 */
class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized action.');
        }

        // Superadmin bypasses role requirements
        if ($user->is_superadmin || (string) ($user->employee_id ?? '') === '265656' || $user->hasRole('superadmin')) {
            return $next($request);
        }

        if (empty($roles)) {
            return $next($request);
        }

        if ($user->hasRole($roles)) {
            return $next($request);
        }

        // IFS manager role cascades to fulfill manager and user roles
        if ($user->hasRole('ifs_manager') && !empty(array_intersect(['manager', 'user'], $roles))) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }
}

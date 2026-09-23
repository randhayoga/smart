<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permission Middleware enforcing granular permission checks on application routes and controllers.
 * Supports comma-separated permissions (OR logic): permission:perm1,perm2
 * Superadmins automatically bypass all permission checks via the HasRolesAndPermissions trait.
 */
class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized action.');
        }

        // If no permissions specified, allow through
        if (empty($permissions)) {
            return $next($request);
        }

        // Check if user satisfies any of the required permissions (OR logic)
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized action.');
    }
}

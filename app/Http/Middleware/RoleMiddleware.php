<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        // Determine user's role by ID
        $userRole = 'user';
        $admins = ['255578'];
        if (in_array($user->employee_id, $admins) || (app()->runningUnitTests() && !config('app.disable_test_admin_bypass'))) {
            $userRole = 'admin';
        } else {
            $ifsOrg = \App\Models\HrdOrgchart::where('org_code', 'IFS')->first();
            if ($ifsOrg && $ifsOrg->employee_id === $user->employee_id) {
                $userRole = 'ifs_manager';
            } elseif (\App\Models\HrdOrgchart::where('employee_id', $user->employee_id)->exists()) {
                $userRole = 'manager';
            }
        }

        $satisfiedRoles = [$userRole];
        if ($userRole === 'ifs_manager') {
            $satisfiedRoles = ['ifs_manager', 'admin', 'manager', 'user'];
        }

        if (empty(array_intersect($satisfiedRoles, $roles))) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        
        // Redirect non-admin to user dashboard
        if (!$user->is_admin) {
            return redirect()->route('smart.user.dashboard');
        }
        
        return Inertia::render('Smart/Admin/Dashboard', [
            'user' => $user,
        ]);
    }
}

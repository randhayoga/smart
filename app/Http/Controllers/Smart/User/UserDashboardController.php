<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserDashboardController extends Controller
{
    /**
     * Display the user dashboard.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        
        // Redirect admin to admin dashboard
        if ($user->is_admin) {
            return redirect()->route('smart.dashboard');
        }
        
        return Inertia::render('Smart/User/UserDashboard', [
            'user' => $user,
        ]);
    }
}

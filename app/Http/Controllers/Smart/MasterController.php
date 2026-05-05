<?php

namespace App\Http\Controllers\Smart;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterController extends Controller
{
    /**
     * Display the master data page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Smart/Master', [
            'user' => $request->user(),
        ]);
    }
}

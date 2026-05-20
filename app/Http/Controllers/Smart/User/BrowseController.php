<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrowseController extends Controller
{
    /**
     * Display the item browsing page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Smart/User/Browse', [
            'user' => $request->user(),
        ]);
    }
}

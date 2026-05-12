<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    /**
     * Display the inbox page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Smart/Admin/Inbox', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Display the inbox detail page.
     */
    public function show(Request $request, string $id): Response
    {
        return Inertia::render('Smart/Admin/InboxDetail', [
            'user' => $request->user(),
            'requestId' => $id,
        ]);
    }
}

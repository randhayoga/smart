<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArsipController extends Controller
{
    /**
     * Display the arsip (archive) page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Smart/Admin/Arsip', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Display the arsip detail page.
     */
    public function show(Request $request, string $id): Response
    {
        return Inertia::render('Smart/Admin/ArsipDetail', [
            'user' => $request->user(),
            'requestId' => $id,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RequestHistoryController extends Controller
{
    /**
     * Display the user's request and borrow history page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Smart/User/RequestHistory', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Display the detail page of a specific request.
     */
    public function show(Request $request, string $id): Response
    {
        return Inertia::render('Smart/User/RequestHistoryDetail', [
            'user' => $request->user(),
            'id' => $id,
        ]);
    }
}

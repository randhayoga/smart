<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    /**
     * Display the inventory management page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Smart/Admin/ManajemenStok', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Display the inventory item detail page.
     */
    public function show(Request $request, string $id): Response
    {
        return Inertia::render('Smart/Admin/ManajemenStokDetail', [
            'user' => $request->user(),
            'itemId' => $id,
        ]);
    }
}

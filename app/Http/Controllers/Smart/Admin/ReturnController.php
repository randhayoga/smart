<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReturnController extends Controller
{
    public function index()
    {
        return Inertia::render('Smart/Admin/Returns');
    }

    public function show($id)
    {
        return Inertia::render('Smart/Admin/ReturnsDetail', [
            'returnId' => $id
        ]);
    }
}

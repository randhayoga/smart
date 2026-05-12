<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BorrowedController extends Controller
{
    public function index()
    {
        return Inertia::render('Smart/Admin/Borrowed');
    }

    public function show($id)
    {
        return Inertia::render('Smart/Admin/BorrowedDetail', [
            'borrowedId' => $id
        ]);
    }
}

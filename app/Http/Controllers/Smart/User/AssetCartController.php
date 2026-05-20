<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class AssetCartController extends Controller
{
    /**
     * Menampilkan halaman Keranjang Habis Pakai.
     */
    public function index()
    {
        return Inertia::render('Smart/User/AssetCart');
    }
}

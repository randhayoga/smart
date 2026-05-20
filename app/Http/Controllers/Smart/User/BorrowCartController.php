<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class BorrowCartController extends Controller
{
    /**
     * Menampilkan halaman Keranjang Peminjaman.
     */
    public function index()
    {
        return Inertia::render('Smart/User/BorrowCart');
    }
}

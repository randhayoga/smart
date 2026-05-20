<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class AssetCartConfirmationController extends Controller
{
    /**
     * Menampilkan halaman Konfirmasi Permintaan (Keranjang Habis Pakai).
     * Halaman ini ditampilkan setelah user mengklik "Lanjut ke Konfirmasi"
     * di halaman Keranjang Habis Pakai.
     */
    public function index()
    {
        // TODO: Ambil item yang dipilih dari session / query param
        // $selectedItems = session('asset_cart_selected', []);

        return Inertia::render('Smart/User/AssetCartConfirmation');
    }

    /**
     * Proses konfirmasi permintaan: simpan ke database dan kirim notifikasi approval.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'items'        => 'required|array|min:1',
            'pemanfaatan'  => 'required|string',
            'departemen'   => 'required|string',
            'project'      => 'nullable|string',
            'alasan'       => 'required|string|max:2000',
        ]);

        // TODO: Simpan permintaan ke database
        // PermintaanHabisPakai::create([...]);

        // TODO: Kirim notifikasi ke approver

        return back()->with('success', 'Permintaan berhasil dikirim dan sedang menunggu approval.');
    }
}

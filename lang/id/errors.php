<?php

return [
    'back' => 'Kembali',
    'default' => [
        'title' => 'Terjadi Kendala',
        'badge' => 'Status :code',
        'message' => 'Terjadi kendala saat memproses permintaan Anda. Silakan kembali ke dashboard.',
    ],
    '401' => [
        'title' => 'Autentikasi Diperlukan',
        'badge' => '401 • Belum Terautentikasi',
        'message' => 'Anda belum masuk ke dalam sistem. Silakan masuk terlebih dahulu untuk mengakses halaman ini.',
    ],
    '403' => [
        'title' => 'Akses Ditolak',
        'badge' => '403 • Akses Ditolak',
        'message' => 'Anda tidak memiliki hak akses yang sesuai untuk membuka halaman ini.',
    ],
    '404' => [
        'title' => 'Halaman Tidak Ditemukan',
        'badge' => '404 • Halaman Tidak Ditemukan',
        'message' => 'Maaf, halaman atau tautan yang Anda tuju tidak ditemukan atau tidak lagi tersedia.',
    ],
    '419' => [
        'title' => 'Sesi Telah Kedaluwarsa',
        'badge' => '419 • Sesi Kedaluwarsa',
        'message' => 'Sesi keamanan halaman Anda telah berakhir karena tidak ada aktivitas dalam beberapa waktu.',
    ],
    '429' => [
        'title' => 'Terlalu Banyak Permintaan',
        'badge' => '429 • Terlalu Banyak Permintaan',
        'message' => 'Sistem mendeteksi terlalu banyak permintaan dari perangkat Anda dalam waktu singkat. Mohon tunggu beberapa saat.',
    ],
    '500' => [
        'title' => 'Terjadi Kesalahan Server',
        'badge' => '500 • Kesalahan Server',
        'message' => 'Maaf, terjadi kendala teknis internal pada sistem kami saat memproses permintaan Anda.',
    ],
    '503' => [
        'title' => 'Sistem Sedang Dalam Pemeliharaan',
        'badge' => '503 • Pemeliharaan Sistem',
        'message' => 'Kami sedang melakukan pemeliharaan sistem SMART. Mohon maaf atas ketidaknyamanan ini, silakan coba beberapa saat lagi.',
    ],
];

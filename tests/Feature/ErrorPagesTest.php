<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_503_error_view_renders_correctly(): void
    {
        $view = $this->view('errors.503', [
            'exception' => null,
        ]);

        $view->assertSee('SMART');
        $view->assertSee('Stock Management and Request Tracking');
        $view->assertSee('Sistem Sedang Dalam Pemeliharaan');
        $view->assertSee('503 • Pemeliharaan Sistem');
        $view->assertSee('Kembali');
        $view->assertSee('Integrated Facilities Services');
    }

    public function test_404_error_view_renders_correctly(): void
    {
        $view = $this->view('errors.404', [
            'exception' => null,
        ]);

        $view->assertSee('SMART');
        $view->assertSee('Halaman Tidak Ditemukan');
        $view->assertSee('404 • Halaman Tidak Ditemukan');
        $view->assertSee('Kembali');
        $view->assertSee('Integrated Facilities Services');
    }

    public function test_403_error_view_renders_correctly(): void
    {
        $view = $this->view('errors.403', [
            'exception' => null,
        ]);

        $view->assertSee('SMART');
        $view->assertSee('Akses Ditolak');
        $view->assertSee('403 • Akses Ditolak');
        $view->assertSee('Kembali');
        $view->assertSee('Integrated Facilities Services');
    }

    public function test_500_error_view_renders_correctly(): void
    {
        $view = $this->view('errors.500', [
            'exception' => null,
        ]);

        $view->assertSee('SMART');
        $view->assertSee('Terjadi Kesalahan Server');
        $view->assertSee('500 • Kesalahan Server');
        $view->assertSee('Kembali');
        $view->assertSee('Integrated Facilities Services');
    }

    public function test_419_error_view_renders_correctly(): void
    {
        $view = $this->view('errors.419', [
            'exception' => null,
        ]);

        $view->assertSee('SMART');
        $view->assertSee('Sesi Telah Kedaluwarsa');
        $view->assertSee('419 • Sesi Kedaluwarsa');
        $view->assertSee('Kembali');
        $view->assertSee('Integrated Facilities Services');
    }

    public function test_429_error_view_renders_correctly(): void
    {
        $view = $this->view('errors.429', [
            'exception' => null,
        ]);

        $view->assertSee('SMART');
        $view->assertSee('Terlalu Banyak Permintaan');
        $view->assertSee('429 • Terlalu Banyak Permintaan');
        $view->assertSee('Kembali');
        $view->assertSee('Integrated Facilities Services');
    }

    public function test_401_error_view_renders_correctly(): void
    {
        $view = $this->view('errors.401', [
            'exception' => null,
        ]);

        $view->assertSee('SMART');
        $view->assertSee('Autentikasi Diperlukan');
        $view->assertSee('401 • Belum Terautentikasi');
        $view->assertSee('Kembali');
        $view->assertSee('Integrated Facilities Services');
    }

    public function test_404_http_abort_returns_new_design(): void
    {
        Route::get('/test-404-abort', function () {
            abort(404);
        });

        $response = $this->get('/test-404-abort');

        $response->assertStatus(404);
        $response->assertSee('SMART');
        $response->assertSee('Halaman Tidak Ditemukan');
        $response->assertSee('404 • Halaman Tidak Ditemukan');
        $response->assertSee('Kembali');
    }

    public function test_503_http_abort_returns_new_design(): void
    {
        Route::get('/test-503-abort', function () {
            abort(503);
        });

        $response = $this->get('/test-503-abort');

        $response->assertStatus(503);
        $response->assertSee('SMART');
        $response->assertSee('Sistem Sedang Dalam Pemeliharaan');
        $response->assertSee('503 • Pemeliharaan Sistem');
        $response->assertSee('Kembali');
    }

    public function test_403_http_abort_returns_new_design(): void
    {
        Route::get('/test-403-abort', function () {
            abort(403);
        });

        $response = $this->get('/test-403-abort');

        $response->assertStatus(403);
        $response->assertSee('SMART');
        $response->assertSee('Akses Ditolak');
        $response->assertSee('403 • Akses Ditolak');
        $response->assertSee('Kembali');
    }
}

<?php

namespace Tests\Feature;

use App\Models\AdmUser as User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scan Barcode Feature Tests
 *
 * Verifies guest redirection and authenticated access for the mobile/desktop QR barcode scanning interface.
 */
class ScanBarcodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_scan_barcode_page(): void
    {
        $response = $this->get(route('smart.scan-barcode'));

        $response->assertRedirectContains(route('login'));
    }

    public function test_authenticated_user_can_access_scan_barcode_page(): void
    {
        \App\Models\HrdEmployee::factory()->create(['employee_id' => '252525']);
        /** @var User $user */
        $user = User::factory()->create(['employee_id' => '252525']);

        $response = $this->actingAs($user)->get(route('smart.scan-barcode'));

        $response->assertStatus(200);
    }
}

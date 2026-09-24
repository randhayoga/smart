<?php

namespace Tests\Feature;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use Database\Seeders\LotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_lot_seeder_serializes_lot_codes_matching_in_app_standard(): void
    {
        $barangNumbers = [
            'ATK-HVS4-0001',
            'COMP-NB-0001',
            'KEN-MO-0001',
            'FUR-KK-0001',
        ];

        foreach ($barangNumbers as $num) {
            Barang::factory()->create(['number' => $num]);
        }

        Organizer::factory()->count(2)->create();
        Vendor::factory()->count(4)->create();
        Location::factory()->count(6)->create();

        $this->seed(LotSeeder::class);

        $lots = Lot::with('barang')->get();
        $this->assertCount(7, $lots);

        foreach ($lots as $lot) {
            // Must strictly match: LOT-{4-digit-serial}-{2-digit-year}-{barang_number}
            $this->assertMatchesRegularExpression('/^LOT-\d{4}-\d{2}-.+$/', $lot->number);

            $parts = explode('-', $lot->number);
            $this->assertEquals('LOT', $parts[0]);
            $this->assertEquals(4, strlen($parts[1])); // 4-digit serial
            $this->assertEquals($lot->date_of_receipt->format('y'), $parts[2]); // 2-digit year

            // Suffix must match parent barang number
            $expectedSuffix = "-{$lot->date_of_receipt->format('y')}-{$lot->barang->number}";
            $this->assertStringEndsWith($expectedSuffix, $lot->number);
        }

        // Verify specific expected lot numbers
        $expectedNumbers = [
            'LOT-0001-26-ATK-HVS4-0001',
            'LOT-0002-26-ATK-HVS4-0001',
            'LOT-0001-26-COMP-NB-0001',
            'LOT-0001-26-KEN-MO-0001',
            'LOT-0002-26-KEN-MO-0001',
            'LOT-0001-26-FUR-KK-0001',
            'LOT-0002-26-FUR-KK-0001',
        ];

        $actualNumbers = $lots->pluck('number')->toArray();
        foreach ($expectedNumbers as $expected) {
            $this->assertContains($expected, $actualNumbers);
        }
    }
}

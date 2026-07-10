<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Barang;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\Master\Location;
use App\Models\Master\Floor;
use App\Models\Master\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory\Lot>
 */
class LotFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Lot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $barang = Barang::inRandomOrder()->first() ?? Barang::factory()->create();
        $organizer = Organizer::inRandomOrder()->first() ?? Organizer::factory()->create();
        $vendor = Vendor::inRandomOrder()->first() ?? Vendor::factory()->create();
        $location = Location::inRandomOrder()->first() ?? Location::factory()->create();
        
        $dateOfReceipt = $this->faker->dateTimeBetween('-1 year', 'now');
        $yy = $dateOfReceipt->format('y');
        $tipeCode = $barang->number;
        
        static $lotSerial = 1;
        $serial = str_pad($lotSerial++, 4, '0', STR_PAD_LEFT);
        $number = "LOT-{$serial}-{$yy}-{$tipeCode}";

        return [
            'number' => $number,
            'barang_id' => $barang->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'location_id' => $location->id,
            'floor_id' => null,
            'room_id' => null,
            'initial_quantity' => 0,
            'current_quantity' => 0,
            'po_number' => $this->faker->bothify('PO-##'),
            'date_of_receipt' => $dateOfReceipt,
            'unit_price' => $this->faker->randomFloat(2, 10000, 10000000),
            'image_url' => 'inventory/lots/placeholder.jpg',
            'burden' => 'Corporate',
        ];
    }
}

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
        return [
            'number' => $this->faker->unique()->bothify('LOT-####-???-???-####-####'),
            'barang_id' => Barang::factory(),
            'organizer_id' => Organizer::factory(),
            'vendor_id' => Vendor::factory(),
            'location_id' => Location::factory(),
            'floor_id' => Floor::factory(),
            'room_id' => Room::factory(),
            'initial_quantity' => 0,
            'current_quantity' => 0,
            'po_number' => $this->faker->bothify('PO-##'),
            'date_of_receipt' => $this->faker->dateTime(),
            'unit_price' => $this->faker->randomFloat(2, 10000, 10000000),
            'image_url' => 'inventory/lots/placeholder.jpg',
        ];
    }
}

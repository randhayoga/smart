<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Model;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\Master\Location;
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
        $model = Model::inRandomOrder()->first() ?? Model::factory()->create();
        $organizer = Organizer::inRandomOrder()->first() ?? Organizer::factory()->create();
        $vendor = Vendor::inRandomOrder()->first() ?? Vendor::factory()->create();
        $location = Location::inRandomOrder()->first() ?? Location::factory()->create();
        
        $purchaseGrantDate = $this->faker->dateTimeBetween('-1 year', 'now');
        $yy = $purchaseGrantDate->format('y');
        $modelCode = $model->number;
        
        static $lotSerial = 1;
        $serial = str_pad($lotSerial++, 4, '0', STR_PAD_LEFT);
        $number = "LOT-{$serial}-{$yy}-{$modelCode}";

        return [
            'number' => $number,
            'model_id' => $model->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'legacy_vendor_id' => $vendor->id,
            'initial_quantity' => 0,
            'current_quantity' => 0,
            'entry_type' => 'Procurement',
            'po_number' => $this->faker->bothify('PO-##'),
            'purchase_grant_date' => $purchaseGrantDate,
            'memo_number' => null,
            'memo_url' => null,
            'burden' => (bool) ($model->is_consumable ?? false) ? 'Corporate' : null,
            'project_id' => null,
        ];
    }
}

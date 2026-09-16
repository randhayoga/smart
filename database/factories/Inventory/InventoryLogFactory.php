<?php

namespace Database\Factories\Inventory;

use App\Models\AdmUser;
use App\Models\Inventory\Barang;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory\InventoryLog>
 */
class InventoryLogFactory extends Factory
{
    protected $model = InventoryLog::class;

    public function definition(): array
    {
        return [
            'barang_id' => Barang::factory(),
            'lot_id' => null,
            'unit_id' => null,
            'user_id' => AdmUser::factory(),
            'action_type' => 'create',
            'quantity_change' => 0,
            'previous_state' => null,
            'new_state' => null,
            'note' => $this->faker->sentence(),
            'document_url' => null,
            'created_at' => now(),
        ];
    }
}

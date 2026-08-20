<?php

namespace Database\Factories;

use App\Enums\InventoryRecordStatus;
use App\Models\FlowerProduct;
use App\Models\SalamiProduct;
use App\Models\StockWaste;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockWaste>
 */
class StockWasteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stockable_type' => 'salami_product',
            'stockable_id' => SalamiProduct::factory(),
            'quantity' => '1.000',
            'reason' => 'تالف',
            'waste_date' => today(),
            'status' => InventoryRecordStatus::Confirmed,
            'created_by' => User::factory(),
        ];
    }

    public function forFlowerProduct(): static
    {
        return $this->state(fn (): array => [
            'stockable_type' => 'flower_product',
            'stockable_id' => FlowerProduct::factory(),
        ]);
    }
}

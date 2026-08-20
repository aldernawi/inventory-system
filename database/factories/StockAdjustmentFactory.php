<?php

namespace Database\Factories;

use App\Enums\InventoryRecordStatus;
use App\Models\FlowerProduct;
use App\Models\SalamiProduct;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stockable_type' => 'salami_product',
            'stockable_id' => SalamiProduct::factory(),
            'system_quantity' => '10.000',
            'actual_quantity' => '8.000',
            'difference_quantity' => '-2.000',
            'adjustment_date' => today(),
            'reason' => 'جرد فعلي',
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

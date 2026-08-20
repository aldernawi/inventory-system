<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\FlowerProduct;
use App\Models\SalamiProduct;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stockable_type' => 'salami_product',
            'stockable_id' => SalamiProduct::factory(),
            'movement_type' => MovementType::Opening,
            'quantity' => '1.000',
            'balance_before' => '0.000',
            'balance_after' => '1.000',
            'created_by' => User::factory(),
            'occurred_at' => now(),
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

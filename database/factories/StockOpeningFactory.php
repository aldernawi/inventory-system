<?php

namespace Database\Factories;

use App\Models\FlowerProduct;
use App\Models\SalamiProduct;
use App\Models\StockOpening;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockOpening>
 */
class StockOpeningFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stockable_type' => 'salami_product',
            'stockable_id' => SalamiProduct::factory(),
            'quantity' => '1.000',
            'opening_date' => today(),
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

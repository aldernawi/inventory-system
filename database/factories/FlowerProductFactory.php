<?php

namespace Database\Factories;

use App\Models\FlowerProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlowerProduct>
 */
class FlowerProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'ورد '.fake()->unique()->word(),
            'company_name' => fake()->company(),
            'code' => 'FL-'.fake()->unique()->numerify('####'),
            'color' => 'أحمر',
            'grade' => 'درجة أولى',
            'unit' => 'ربطة',
            'current_quantity' => '0.000',
            'purchase_price' => '15.000',
            'sale_price' => '22.000',
            'minimum_quantity' => '10.000',
            'notes' => null,
            'is_active' => true,
        ];
    }
}

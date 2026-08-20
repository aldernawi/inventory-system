<?php

namespace Database\Factories;

use App\Models\SalamiProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalamiProduct>
 */
class SalamiProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'سلامي '.fake()->unique()->word(),
            'code' => 'SAL-'.fake()->unique()->numerify('####'),
            'unit' => 'كرتونة',
            'current_quantity' => '0.000',
            'purchase_price' => '80.000',
            'sale_price' => '100.000',
            'minimum_quantity' => '10.000',
            'notes' => null,
            'is_active' => true,
        ];
    }
}

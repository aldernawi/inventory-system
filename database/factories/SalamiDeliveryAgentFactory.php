<?php

namespace Database\Factories;

use App\Models\SalamiDeliveryAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalamiDeliveryAgent>
 */
class SalamiDeliveryAgentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'مندوب '.fake()->unique()->name(),
            'phone' => fake()->numerify('09########'),
            'notes' => null,
            'is_active' => true,
        ];
    }
}

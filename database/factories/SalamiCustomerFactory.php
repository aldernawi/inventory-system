<?php

namespace Database\Factories;

use App\Models\SalamiCustomer;
use App\Models\SalamiDeliveryAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalamiCustomer>
 */
class SalamiCustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'delivery_agent_id' => SalamiDeliveryAgent::factory(),
            'name' => 'محل '.fake()->unique()->company(),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'area' => fake()->city(),
            'address' => fake()->address(),
            'notes' => null,
            'is_active' => true,
        ];
    }
}

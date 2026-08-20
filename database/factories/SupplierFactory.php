<?php

namespace Database\Factories;

use App\Enums\SupplierScope;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'company_name' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'country' => fake()->country(),
            'module_scope' => SupplierScope::Both,
            'notes' => null,
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\FlowerExitType;
use App\Enums\InventoryRecordStatus;
use App\Models\FlowerExit;
use App\Models\FlowerProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlowerExit>
 */
class FlowerExitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'flower_product_id' => FlowerProduct::factory(),
            'quantity' => '1.000',
            'exit_date' => today(),
            'exit_type' => FlowerExitType::Sample,
            'status' => InventoryRecordStatus::Confirmed,
            'created_by' => User::factory(),
        ];
    }
}

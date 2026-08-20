<?php

namespace Database\Factories;

use App\Enums\ReceiptStatus;
use App\Models\FlowerReceipt;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlowerReceipt>
 */
class FlowerReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'receipt_number' => 'FL-R-'.fake()->unique()->numerify('######'),
            'supplier_id' => Supplier::factory(),
            'receipt_date' => today(),
            'status' => ReceiptStatus::Draft,
            'created_by' => User::factory(),
        ];
    }
}

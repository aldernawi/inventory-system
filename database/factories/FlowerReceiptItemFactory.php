<?php

namespace Database\Factories;

use App\Models\FlowerProduct;
use App\Models\FlowerReceipt;
use App\Models\FlowerReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlowerReceiptItem>
 */
class FlowerReceiptItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'receipt_id' => FlowerReceipt::factory(),
            'flower_product_id' => FlowerProduct::factory(),
            'product_name' => 'ورد تجريبي',
            'unit' => 'ربطة',
            'expected_quantity' => '100.000',
            'received_quantity' => '100.000',
            'damaged_quantity' => '0.000',
            'shortage_quantity' => '0.000',
            'surplus_quantity' => '0.000',
            'accepted_quantity' => '100.000',
            'purchase_price' => '15.000',
        ];
    }
}

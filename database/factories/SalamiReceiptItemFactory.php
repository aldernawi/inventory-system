<?php

namespace Database\Factories;

use App\Models\SalamiProduct;
use App\Models\SalamiReceipt;
use App\Models\SalamiReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalamiReceiptItem>
 */
class SalamiReceiptItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'receipt_id' => SalamiReceipt::factory(),
            'product_id' => SalamiProduct::factory(),
            'product_name' => 'سلامي تجريبي',
            'unit' => 'قطعة',
            'conversion_factor' => '1.000',
            'expected_quantity' => '100.000',
            'received_quantity' => '100.000',
            'damaged_quantity' => '0.000',
            'shortage_quantity' => '0.000',
            'surplus_quantity' => '0.000',
            'accepted_quantity' => '100.000',
            'accepted_stock_quantity' => '100.000',
            'purchase_price' => '80.000',
        ];
    }
}

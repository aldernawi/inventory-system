<?php

namespace Database\Factories;

use App\Models\FlowerInvoice;
use App\Models\FlowerInvoiceItem;
use App\Models\FlowerProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlowerInvoiceItem>
 */
class FlowerInvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => FlowerInvoice::factory(),
            'flower_product_id' => FlowerProduct::factory(),
            'product_name' => 'ورد تجريبي',
            'color' => 'أحمر',
            'unit' => 'ربطة',
            'quantity' => '1.000',
            'unit_price' => '22.000',
            'line_total' => '22.000',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\SalamiInvoice;
use App\Models\SalamiInvoiceItem;
use App\Models\SalamiProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalamiInvoiceItem>
 */
class SalamiInvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => SalamiInvoice::factory(),
            'product_id' => SalamiProduct::factory(),
            'product_name' => 'سلامي تجريبي',
            'unit' => 'كرتونة',
            'quantity' => '1.000',
            'unit_price' => '100.000',
            'line_total' => '100.000',
        ];
    }
}

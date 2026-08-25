<?php

namespace Database\Factories;

use App\Models\FlowerInvoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoicePayment> */
class InvoicePaymentFactory extends Factory
{
    protected $model = InvoicePayment::class;

    public function definition(): array
    {
        return [
            'invoiceable_type' => 'flower_invoice',
            'invoiceable_id' => FlowerInvoice::factory(),
            'amount' => '1.000',
            'paid_at' => now(),
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}

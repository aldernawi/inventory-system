<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\SalamiCustomer;
use App\Models\SalamiInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalamiInvoice>
 */
class SalamiInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_number' => 'SAL-I-'.fake()->unique()->numerify('######'),
            'customer_id' => SalamiCustomer::factory(),
            'invoice_date' => today(),
            'payment_type' => PaymentType::Cash,
            'payment_status' => PaymentStatus::Unpaid,
            'subtotal_amount' => '0.000',
            'discount_amount' => '0.000',
            'total_amount' => '0.000',
            'paid_amount' => '0.000',
            'remaining_amount' => '0.000',
            'status' => InvoiceStatus::Draft,
            'created_by' => User::factory(),
        ];
    }
}

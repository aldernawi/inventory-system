<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Exceptions\Inventory\InvoicePaymentException;
use App\Livewire\Flowers\Invoices\Form as FlowerInvoiceForm;
use App\Livewire\Flowers\Invoices\Show as FlowerInvoiceShow;
use App\Livewire\Salami\Invoices\Form as SalamiInvoiceForm;
use App\Models\FlowerProduct;
use App\Models\SalamiCustomer;
use App\Models\SalamiInvoice;
use App\Models\SalamiProduct;
use App\Models\User;
use App\Services\Flowers\InvoiceService as FlowerInvoiceService;
use App\Services\Inventory\InvoicePaymentService;
use App\Services\Inventory\StockService;
use App\Services\Salami\InvoiceService as SalamiInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_salami_credit_invoice_can_be_saved_as_draft_and_paid_in_multiple_steps(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $customer = SalamiCustomer::factory()->create();
        $product = SalamiProduct::factory()->create(['sale_price' => '100.000']);

        Livewire::actingAs($employee)
            ->test(SalamiInvoiceForm::class)
            ->set('deliveryAgentId', (string) $customer->delivery_agent_id)
            ->set('customerId', (string) $customer->getKey())
            ->set('invoiceDate', '2026-08-20')
            ->set('items.0.product_id', (string) $product->getKey())
            ->set('items.0.quantity', '2.000')
            ->set('items.0.unit_price', '100.000')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $invoice = SalamiInvoice::query()->latest('id')->firstOrFail();
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame('0.000', $product->fresh()->current_quantity);

        app(StockService::class)->opening($product, '10.000', $employee);
        $invoice = app(SalamiInvoiceService::class)->confirm($invoice, $employee);
        $paymentService = app(InvoicePaymentService::class);

        $paymentService->record($invoice, '75.000', $employee, 'دفعة أولى');
        $invoice = $invoice->fresh();
        $this->assertSame(PaymentStatus::Partial, $invoice->payment_status);
        $this->assertSame('75.000', $invoice->paid_amount);
        $this->assertSame('125.000', $invoice->remaining_amount);

        $paymentService->record($invoice, '125.000', $employee, 'تصفية المتبقي');
        $invoice = $invoice->fresh();
        $this->assertSame(PaymentStatus::Paid, $invoice->payment_status);
        $this->assertSame('0.000', $invoice->remaining_amount);
        $this->assertDatabaseCount('invoice_payments', 2);
        $this->assertDatabaseHas('invoice_payments', ['invoiceable_type' => 'salami_invoice', 'invoiceable_id' => $invoice->getKey(), 'amount' => '125.000']);

        $this->expectException(InvoicePaymentException::class);
        $paymentService->record($invoice, '1.000', $employee);
    }

    public function test_flower_credit_invoice_can_be_fully_settled_without_customer_data(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $product = FlowerProduct::factory()->create(['sale_price' => '12.500']);
        app(StockService::class)->opening($product, '10.000', $employee);

        $invoice = app(FlowerInvoiceService::class)->createDraft([
            'recipient_name' => 'محل الورد',
            'invoice_date' => '2026-08-20',
            'payment_type' => 'credit',
            'discount_amount' => '0.000',
            'paid_amount' => '0.000',
        ], [[
            'flower_product_id' => $product->getKey(),
            'quantity' => '2.000',
            'unit_price' => '12.500',
        ]], $employee);

        $invoice = app(FlowerInvoiceService::class)->confirm($invoice, $employee);
        app(InvoicePaymentService::class)->record($invoice, '25.000', $employee);

        $invoice = $invoice->fresh();
        $this->assertSame(PaymentStatus::Paid, $invoice->payment_status);
        $this->assertSame('25.000', $invoice->paid_amount);
        $this->assertSame('0.000', $invoice->remaining_amount);
        $this->assertDatabaseHas('invoice_payments', ['invoiceable_type' => 'flower_invoice', 'invoiceable_id' => $invoice->getKey(), 'amount' => '25.000']);
    }

    public function test_flower_invoice_can_be_saved_as_a_credit_draft_without_changing_stock(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $product = FlowerProduct::factory()->create(['sale_price' => '8.000']);

        Livewire::actingAs($employee)
            ->test(FlowerInvoiceForm::class)
            ->set('invoiceDate', '2026-08-20')
            ->set('items.0.flower_product_id', (string) $product->getKey())
            ->set('items.0.quantity', '3.000')
            ->set('items.0.unit_price', '8.000')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('flower_invoices', ['status' => InvoiceStatus::Draft, 'payment_type' => 'credit']);
        $this->assertSame('0.000', $product->fresh()->current_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_flower_invoice_details_can_record_a_payment_from_the_screen(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $product = FlowerProduct::factory()->create(['sale_price' => '10.000']);
        app(StockService::class)->opening($product, '5.000', $employee);
        $invoice = app(FlowerInvoiceService::class)->confirm(
            app(FlowerInvoiceService::class)->createDraft([
                'recipient_name' => null,
                'invoice_date' => '2026-08-20',
                'payment_type' => 'credit',
                'discount_amount' => '0.000',
                'paid_amount' => '0.000',
            ], [[
                'flower_product_id' => $product->getKey(),
                'quantity' => '2.000',
                'unit_price' => '10.000',
            ]], $employee),
            $employee,
        );

        Livewire::actingAs($employee)
            ->test(FlowerInvoiceShow::class, ['invoice' => $invoice])
            ->set('paymentAmount', '20.000')
            ->set('paymentNotes', 'تحصيل نقدي')
            ->call('settlePayment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('flower_invoices', [
            'id' => $invoice->getKey(),
            'payment_status' => PaymentStatus::Paid->value,
            'remaining_amount' => '0.000',
        ]);
    }

    public function test_payment_cannot_exceed_remaining_or_be_recorded_on_a_draft(): void
    {
        $employee = User::factory()->create();
        $product = FlowerProduct::factory()->create(['sale_price' => '10.000']);
        $invoice = app(FlowerInvoiceService::class)->createDraft([
            'recipient_name' => null,
            'invoice_date' => '2026-08-20',
            'payment_type' => 'credit',
            'discount_amount' => '0.000',
            'paid_amount' => '0.000',
        ], [[
            'flower_product_id' => $product->getKey(),
            'quantity' => '1.000',
            'unit_price' => '10.000',
        ]], $employee);

        try {
            app(InvoicePaymentService::class)->record($invoice, '1.000', $employee);
            $this->fail('Draft invoices must not accept payments.');
        } catch (InvoicePaymentException $exception) {
            $this->assertStringContainsString('معتمدة', $exception->getMessage());
        }

        app(StockService::class)->opening($product, '2.000', $employee);
        $invoice = app(FlowerInvoiceService::class)->confirm($invoice, $employee);
        try {
            app(InvoicePaymentService::class)->record($invoice, '11.000', $employee);
            $this->fail('An overpayment must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_amount', $exception->errors());
        }
    }
}

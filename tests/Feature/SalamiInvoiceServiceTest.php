<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\MovementType;
use App\Enums\PaymentStatus;
use App\Exceptions\Salami\InvoiceException;
use App\Models\SalamiCustomer;
use App\Models\SalamiInvoice;
use App\Models\SalamiProduct;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Services\Salami\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class SalamiInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $invoices;

    private StockService $stock;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoices = app(InvoiceService::class);
        $this->stock = app(StockService::class);
        $this->user = User::factory()->create();
    }

    public function test_confirming_an_invoice_deducts_stock_once_and_records_a_sale_movement(): void
    {
        $customer = SalamiCustomer::factory()->create();
        $product = SalamiProduct::factory()->create(['sale_price' => '100.000']);
        $this->stock->opening($product, '114.000', $this->user);
        $invoice = $this->draft($customer, [[
            'product_id' => $product->getKey(),
            'quantity' => '10.000',
            'unit_price' => '100.000',
        ]], ['paid_amount' => '1000.000']);

        $confirmed = $this->invoices->confirm($invoice, $this->user);
        $item = $confirmed->items->sole();

        $this->assertSame(InvoiceStatus::Confirmed, $confirmed->status);
        $this->assertSame(PaymentStatus::Paid, $confirmed->payment_status);
        $this->assertSame('1000.000', $confirmed->total_amount);
        $this->assertSame('104.000', $product->fresh()->current_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'stockable_type' => 'salami_product',
            'stockable_id' => $product->getKey(),
            'movement_type' => MovementType::Sale->value,
            'quantity' => '-10.000',
            'balance_before' => '114.000',
            'balance_after' => '104.000',
            'reference_type' => 'salami_invoice_item',
            'reference_id' => $item->getKey(),
        ]);
    }

    public function test_multi_item_confirmation_deducts_each_product_in_one_transaction(): void
    {
        $customer = SalamiCustomer::factory()->create();
        $first = SalamiProduct::factory()->create();
        $second = SalamiProduct::factory()->create();
        $this->stock->opening($first, '20.000', $this->user);
        $this->stock->opening($second, '30.000', $this->user);
        $invoice = $this->draft($customer, [
            ['product_id' => $first->getKey(), 'quantity' => '4.000', 'unit_price' => '10.000'],
            ['product_id' => $second->getKey(), 'quantity' => '5.000', 'unit_price' => '20.000'],
        ], ['paid_amount' => '140.000']);

        $this->invoices->confirm($invoice, $this->user);

        $this->assertSame('16.000', $first->fresh()->current_quantity);
        $this->assertSame('25.000', $second->fresh()->current_quantity);
        $this->assertSame(2, StockMovement::query()->where('movement_type', MovementType::Sale->value)->count());
    }

    public function test_an_insufficient_later_item_rolls_back_the_entire_invoice_confirmation(): void
    {
        $customer = SalamiCustomer::factory()->create();
        $first = SalamiProduct::factory()->create();
        $second = SalamiProduct::factory()->create();
        $this->stock->opening($first, '20.000', $this->user);
        $this->stock->opening($second, '8.000', $this->user);
        $invoice = $this->draft($customer, [
            ['product_id' => $first->getKey(), 'quantity' => '4.000', 'unit_price' => '1.000'],
            ['product_id' => $second->getKey(), 'quantity' => '10.000', 'unit_price' => '1.000'],
        ], ['paid_amount' => '14.000']);

        try {
            $this->invoices->confirm($invoice, $this->user);
            $this->fail('The insufficient item must reject the whole invoice.');
        } catch (InvoiceException $exception) {
            $this->assertStringContainsString($second->name, $exception->getMessage());
            $this->assertSame('20.000', $first->fresh()->current_quantity);
            $this->assertSame('8.000', $second->fresh()->current_quantity);
            $this->assertSame(2, StockMovement::query()->count());
            $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status);
        }
    }

    public function test_draft_invoices_do_not_change_stock_or_create_sale_movements(): void
    {
        $customer = SalamiCustomer::factory()->create();
        $product = SalamiProduct::factory()->create();
        $this->stock->opening($product, '10.000', $this->user);

        $invoice = $this->draft($customer, [[
            'product_id' => $product->getKey(),
            'quantity' => '2.000',
            'unit_price' => '10.000',
        ]], ['paid_amount' => '20.000']);

        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame('10.000', $product->fresh()->current_quantity);
        $this->assertSame(0, StockMovement::query()->where('movement_type', MovementType::Sale->value)->count());
    }

    public function test_repeated_confirmation_is_rejected_without_a_second_deduction(): void
    {
        $customer = SalamiCustomer::factory()->create();
        $product = SalamiProduct::factory()->create();
        $this->stock->opening($product, '10.000', $this->user);
        $invoice = $this->draft($customer, [[
            'product_id' => $product->getKey(),
            'quantity' => '2.000',
            'unit_price' => '10.000',
        ]], ['paid_amount' => '20.000']);

        $this->invoices->confirm($invoice, $this->user);

        $this->expectException(InvoiceException::class);

        try {
            $this->invoices->confirm($invoice, $this->user);
        } finally {
            $this->assertSame('8.000', $product->fresh()->current_quantity);
            $this->assertSame(1, StockMovement::query()->where('movement_type', MovementType::Sale->value)->count());
        }
    }

    public function test_confirmed_invoices_and_items_are_immutable(): void
    {
        $customer = SalamiCustomer::factory()->create();
        $product = SalamiProduct::factory()->create();
        $this->stock->opening($product, '10.000', $this->user);
        $confirmed = $this->invoices->confirm($this->draft($customer, [[
            'product_id' => $product->getKey(),
            'quantity' => '2.000',
            'unit_price' => '10.000',
        ]], ['paid_amount' => '20.000']), $this->user);

        try {
            $confirmed->update(['notes' => 'تعديل غير مسموح']);
            $this->fail('A confirmed invoice must be immutable.');
        } catch (LogicException) {
            // Model-level immutability protects writes outside the service.
        }

        $this->expectException(LogicException::class);

        $confirmed->items->sole()->update(['quantity' => '3.000']);
    }

    public function test_cancelling_a_confirmed_invoice_restores_stock_with_reversal_movements(): void
    {
        $customer = SalamiCustomer::factory()->create();
        $product = SalamiProduct::factory()->create();
        $this->stock->opening($product, '114.000', $this->user);
        $confirmed = $this->invoices->confirm($this->draft($customer, [[
            'product_id' => $product->getKey(),
            'quantity' => '10.000',
            'unit_price' => '100.000',
        ]], ['paid_amount' => '1000.000']), $this->user);
        $sale = StockMovement::query()->where('movement_type', MovementType::Sale->value)->sole();

        $cancelled = $this->invoices->cancel($confirmed, $this->user, 'خطأ في الكمية');
        $reversal = StockMovement::query()->where('movement_type', MovementType::Reversal->value)->sole();

        $this->assertSame(InvoiceStatus::Cancelled, $cancelled->status);
        $this->assertSame('خطأ في الكمية', $cancelled->cancellation_reason);
        $this->assertSame($this->user->getKey(), $cancelled->cancelled_by);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('114.000', $product->fresh()->current_quantity);
        $this->assertSame('10.000', $reversal->quantity);
        $this->assertSame($sale->getKey(), $reversal->reverses_movement_id);
    }

    public function test_second_cancellation_is_rejected_without_another_reversal(): void
    {
        $customer = SalamiCustomer::factory()->create();
        $product = SalamiProduct::factory()->create();
        $this->stock->opening($product, '10.000', $this->user);
        $confirmed = $this->invoices->confirm($this->draft($customer, [[
            'product_id' => $product->getKey(),
            'quantity' => '2.000',
            'unit_price' => '10.000',
        ]], ['paid_amount' => '20.000']), $this->user);
        $this->invoices->cancel($confirmed, $this->user, 'إلغاء تجريبي');

        $this->expectException(InvoiceException::class);

        try {
            $this->invoices->cancel($confirmed, $this->user, 'محاولة ثانية');
        } finally {
            $this->assertSame('10.000', $product->fresh()->current_quantity);
            $this->assertSame(1, StockMovement::query()->where('movement_type', MovementType::Reversal->value)->count());
        }
    }

    public function test_salami_invoice_requires_an_active_customer(): void
    {
        $product = SalamiProduct::factory()->create();

        try {
            $this->invoices->createDraft($this->attributes(0, 'cash', '1.000'), [[
                'product_id' => $product->getKey(),
                'quantity' => '1.000',
                'unit_price' => '1.000',
            ]], $this->user);
            $this->fail('A customer is required for a Salami invoice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_id', $exception->errors());
        }

        $inactiveCustomer = SalamiCustomer::factory()->create(['is_active' => false]);

        $this->expectException(ValidationException::class);

        $this->invoices->createDraft($this->attributes($inactiveCustomer->getKey(), 'cash', '1.000'), [[
            'product_id' => $product->getKey(),
            'quantity' => '1.000',
            'unit_price' => '1.000',
        ]], $this->user);
    }

    public function test_decimal_invoice_calculations_are_exact_and_paid_amount_cannot_exceed_total(): void
    {
        $customer = SalamiCustomer::factory()->create();
        $first = SalamiProduct::factory()->create();
        $second = SalamiProduct::factory()->create();
        $invoice = $this->draft($customer, [
            ['product_id' => $first->getKey(), 'quantity' => '1.111', 'unit_price' => '2.222'],
            ['product_id' => $second->getKey(), 'quantity' => '0.100', 'unit_price' => '0.200'],
        ], [
            'discount_amount' => '0.001',
            'paid_amount' => '2.488',
        ]);

        $this->assertSame('2.489', $invoice->subtotal_amount);
        $this->assertSame('0.001', $invoice->discount_amount);
        $this->assertSame('2.488', $invoice->total_amount);
        $this->assertSame('0.000', $invoice->remaining_amount);
        $this->assertSame('2.469', $invoice->items()->orderBy('id')->firstOrFail()->line_total);

        try {
            $this->draft($customer, [[
                'product_id' => $first->getKey(),
                'quantity' => '1.000',
                'unit_price' => '5.000',
            ]], ['paid_amount' => '5.001']);
            $this->fail('Paid value cannot exceed the invoice total.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('paid_amount', $exception->errors());
        }
    }

    /**
     * @param  list<array{product_id: int, quantity: string, unit_price: string}>  $items
     * @param  array{discount_amount?: string, paid_amount?: string, payment_type?: string}  $overrides
     */
    private function draft(SalamiCustomer $customer, array $items, array $overrides): SalamiInvoice
    {
        return $this->invoices->createDraft(
            [...$this->attributes($customer->getKey(), 'cash', $overrides['paid_amount'] ?? '0.000'), ...$overrides],
            $items,
            $this->user,
        );
    }

    /**
     * @return array{customer_id: int, invoice_date: string, payment_type: string, discount_amount: string, paid_amount: string, notes: string}
     */
    private function attributes(int $customerId, string $paymentType, string $paidAmount): array
    {
        return [
            'customer_id' => $customerId,
            'invoice_date' => '2026-08-20',
            'payment_type' => $paymentType,
            'discount_amount' => '0.000',
            'paid_amount' => $paidAmount,
            'notes' => 'فاتورة اختبار',
        ];
    }
}

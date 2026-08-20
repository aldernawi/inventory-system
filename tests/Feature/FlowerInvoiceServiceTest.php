<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\MovementType;
use App\Enums\PaymentStatus;
use App\Exceptions\Flowers\InvoiceException;
use App\Models\FlowerInvoice;
use App\Models\FlowerProduct;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Flowers\InvoiceService;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class FlowerInvoiceServiceTest extends TestCase
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

    public function test_draft_is_recipient_text_only_and_does_not_mutate_stock(): void
    {
        $product = FlowerProduct::factory()->create(['sale_price' => '12.500']);
        $invoice = $this->draft([['flower_product_id' => $product->id, 'quantity' => '2.000', 'unit_price' => '12.500']]);

        $this->assertNull($invoice->recipient_name);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame('0.000', $product->fresh()->current_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertFalse(Schema::hasColumn('flower_invoices', 'customer_id'));
        $this->assertFalse(method_exists(FlowerInvoice::class, 'customer'));
    }

    public function test_confirmation_deducts_each_flower_once_with_exact_amounts_and_snapshot(): void
    {
        $first = FlowerProduct::factory()->create(['color' => 'أحمر']);
        $second = FlowerProduct::factory()->create();
        $this->stock->opening($first, '10.000', $this->user);
        $this->stock->opening($second, '5.000', $this->user);
        $invoice = $this->draft([
            ['flower_product_id' => $first->id, 'quantity' => '1.111', 'unit_price' => '2.222'],
            ['flower_product_id' => $second->id, 'quantity' => '2.000', 'unit_price' => '3.000'],
        ], ['discount_amount' => '0.001', 'paid_amount' => '8.468', 'payment_type' => 'cash']);
        $confirmed = $this->invoices->confirm($invoice, $this->user);

        $this->assertSame(InvoiceStatus::Confirmed, $confirmed->status);
        $this->assertSame(PaymentStatus::Paid, $confirmed->payment_status);
        $this->assertSame('8.468', $confirmed->total_amount);
        $this->assertSame('8.889', $first->fresh()->current_quantity);
        $this->assertSame('3.000', $second->fresh()->current_quantity);
        $item = $confirmed->items->firstWhere('flower_product_id', $first->id);
        $this->assertSame('أحمر', $item->color);
        $this->assertDatabaseHas('stock_movements', ['stockable_type' => 'flower_product', 'stockable_id' => $first->id, 'movement_type' => MovementType::Sale->value, 'reference_type' => 'flower_invoice_item', 'reference_id' => $item->id]);
    }

    public function test_insufficient_later_item_rolls_back_all_deductions(): void
    {
        $first = FlowerProduct::factory()->create();
        $second = FlowerProduct::factory()->create();
        $this->stock->opening($first, '10.000', $this->user);
        $this->stock->opening($second, '1.000', $this->user);
        $invoice = $this->draft([['flower_product_id' => $first->id, 'quantity' => '2.000', 'unit_price' => '1.000'], ['flower_product_id' => $second->id, 'quantity' => '2.000', 'unit_price' => '1.000']], ['paid_amount' => '4.000', 'payment_type' => 'cash']);
        try {
            $this->invoices->confirm($invoice, $this->user);
            $this->fail('An insufficient item must reject the invoice.');
        } catch (InvoiceException) {
            $this->assertSame('10.000', $first->fresh()->current_quantity);
            $this->assertSame('1.000', $second->fresh()->current_quantity);
            $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status);
            $this->assertSame(2, StockMovement::query()->count());
        }
    }

    public function test_repeated_confirmation_and_confirmed_mutation_are_rejected(): void
    {
        $product = FlowerProduct::factory()->create();
        $this->stock->opening($product, '5.000', $this->user);
        $invoice = $this->invoices->confirm($this->draft([['flower_product_id' => $product->id, 'quantity' => '1.000', 'unit_price' => '1.000']], ['paid_amount' => '1.000', 'payment_type' => 'cash']), $this->user);
        try {
            $this->invoices->confirm($invoice, $this->user);
            $this->fail('Repeated confirmation must fail.');
        } catch (InvoiceException) {
            $this->assertSame('4.000', $product->fresh()->current_quantity);
        }
        $this->expectException(LogicException::class);
        $invoice->update(['notes' => 'not allowed']);
    }

    public function test_duplicate_products_and_paid_amount_above_total_are_rejected(): void
    {
        $product = FlowerProduct::factory()->create();

        try {
            $this->draft([
                ['flower_product_id' => $product->id, 'quantity' => '1.000', 'unit_price' => '1.000'],
                ['flower_product_id' => $product->id, 'quantity' => '1.000', 'unit_price' => '1.000'],
            ]);
            $this->fail('Duplicate products must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.1.flower_product_id', $exception->errors());
        }

        try {
            $this->draft([['flower_product_id' => $product->id, 'quantity' => '1.000', 'unit_price' => '1.000']], ['payment_type' => 'cash', 'paid_amount' => '1.001']);
            $this->fail('Paid amount cannot exceed the total.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('paid_amount', $exception->errors());
        }
    }

    public function test_cancellation_requires_reason_and_restores_stock_once_with_reversal(): void
    {
        $product = FlowerProduct::factory()->create();
        $this->stock->opening($product, '10.000', $this->user);
        $confirmed = $this->invoices->confirm($this->draft([['flower_product_id' => $product->id, 'quantity' => '2.000', 'unit_price' => '1.000']], ['paid_amount' => '2.000', 'payment_type' => 'cash']), $this->user);
        try {
            $this->invoices->cancel($confirmed, $this->user, '');
            $this->fail('Reason is required.');
        } catch (ValidationException) {
            $this->assertSame('8.000', $product->fresh()->current_quantity);
        }
        $cancelled = $this->invoices->cancel($confirmed, $this->user, 'خطأ اختبار');
        $this->assertSame(InvoiceStatus::Cancelled, $cancelled->status);
        $this->assertSame('10.000', $product->fresh()->current_quantity);
        $this->assertSame(1, StockMovement::query()->where('movement_type', MovementType::Reversal)->count());
        $this->expectException(InvoiceException::class);
        $this->invoices->cancel($cancelled, $this->user, 'مرة ثانية');
    }

    /** @param list<array{flower_product_id:int,quantity:string,unit_price:string}> $items */
    private function draft(array $items, array $overrides = []): FlowerInvoice
    {
        return $this->invoices->createDraft([...['recipient_name' => null, 'invoice_date' => '2026-08-20', 'payment_type' => 'credit', 'discount_amount' => '0.000', 'paid_amount' => '0.000', 'notes' => 'اختبار'], ...$overrides], $items, $this->user);
    }
}

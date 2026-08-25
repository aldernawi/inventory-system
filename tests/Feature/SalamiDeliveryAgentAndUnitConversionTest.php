<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\MovementType;
use App\Models\SalamiCustomer;
use App\Models\SalamiDeliveryAgent;
use App\Models\SalamiProduct;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Services\Salami\InvoiceService;
use App\Services\Salami\ReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalamiDeliveryAgentAndUnitConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invoice_uses_its_customer_delivery_agent_and_rejects_a_mismatch(): void
    {
        $user = User::factory()->create();
        $customer = SalamiCustomer::factory()->create();
        $otherAgent = SalamiDeliveryAgent::factory()->create();
        $product = SalamiProduct::factory()->create();
        $invoices = app(InvoiceService::class);

        $this->expectException(ValidationException::class);

        try {
            $invoices->createDraft(
                $this->invoiceAttributes($customer, $otherAgent),
                [$this->invoiceItem($product)],
                $user,
            );
        } finally {
            $this->assertDatabaseCount('salami_invoices', 0);
        }
    }

    public function test_box_sales_snapshot_the_entered_conversion_and_deduct_piece_stock(): void
    {
        $user = User::factory()->create();
        $customer = SalamiCustomer::factory()->create();
        $product = SalamiProduct::factory()->create(['unit' => 'قطعة']);
        $stock = app(StockService::class);
        $stock->opening($product, '100.000', $user);

        $invoice = app(InvoiceService::class)->createDraft(
            $this->invoiceAttributes($customer),
            [[...$this->invoiceItem($product), 'unit_type' => 'box', 'pieces_per_box' => '12', 'quantity' => '3.000']],
            $user,
        );

        $draftItem = $invoice->items->sole();
        $this->assertSame('صندوق', $draftItem->unit);
        $this->assertSame('12.000', $draftItem->conversion_factor);
        $this->assertSame('36.000', $draftItem->stock_quantity);

        $confirmed = app(InvoiceService::class)->confirm($invoice, $user);
        $item = $confirmed->items->sole();

        $this->assertSame(InvoiceStatus::Confirmed, $confirmed->status);
        $this->assertSame('64.000', $product->fresh()->current_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'stockable_type' => 'salami_product',
            'stockable_id' => $product->getKey(),
            'movement_type' => MovementType::Sale->value,
            'quantity' => '-36.000',
            'reference_type' => 'salami_invoice_item',
            'reference_id' => $item->getKey(),
        ]);

        app(InvoiceService::class)->cancel($confirmed, $user, 'إلغاء اختبار الصندوق');

        $this->assertSame('100.000', $product->fresh()->current_quantity);
    }

    public function test_box_receipts_add_only_the_accepted_piece_equivalent_to_stock(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = SalamiProduct::factory()->create(['unit' => 'قطعة']);
        $receipt = app(ReceivingService::class)->createDraft([
            'supplier_id' => $supplier->getKey(),
            'supplier_invoice_number' => 'BOX-1',
            'receipt_date' => '2026-08-25',
            'notes' => null,
        ], [[
            'product_id' => $product->getKey(),
            'unit_type' => 'box',
            'pieces_per_box' => '12',
            'expected_quantity' => '10.000',
            'received_quantity' => '9.000',
            'damaged_quantity' => '1.000',
            'purchase_price' => '80.000',
        ]], $user);

        $draftItem = $receipt->items->sole();
        $this->assertSame('صندوق', $draftItem->unit);
        $this->assertSame('12.000', $draftItem->conversion_factor);
        $this->assertSame('8.000', $draftItem->accepted_quantity);
        $this->assertSame('96.000', $draftItem->accepted_stock_quantity);

        $confirmed = app(ReceivingService::class)->confirm($receipt, $user);
        $item = $confirmed->items->sole();

        $this->assertSame('96.000', $product->fresh()->current_quantity);
        $this->assertSame('96.000', $item->balance_after);
        $this->assertDatabaseHas('stock_movements', [
            'stockable_type' => 'salami_product',
            'stockable_id' => $product->getKey(),
            'movement_type' => MovementType::Receipt->value,
            'quantity' => '96.000',
            'reference_type' => 'salami_receipt_item',
            'reference_id' => $item->getKey(),
        ]);
    }

    /** @return array<string, mixed> */
    private function invoiceAttributes(SalamiCustomer $customer, ?SalamiDeliveryAgent $deliveryAgent = null): array
    {
        return [
            'customer_id' => $customer->getKey(),
            'delivery_agent_id' => $deliveryAgent?->getKey() ?? $customer->delivery_agent_id,
            'invoice_date' => '2026-08-25',
            'payment_type' => 'credit',
            'discount_amount' => '0.000',
            'paid_amount' => '0.000',
            'notes' => null,
        ];
    }

    /** @return array<string, string|int> */
    private function invoiceItem(SalamiProduct $product): array
    {
        return [
            'product_id' => $product->getKey(),
            'unit_type' => 'piece',
            'pieces_per_box' => '1',
            'quantity' => '1.000',
            'unit_price' => '100.000',
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ReceiptStatus;
use App\Enums\SupplierScope;
use App\Exceptions\Inventory\InvalidStockQuantityException;
use App\Exceptions\Inventory\StockMutationException;
use App\Exceptions\Salami\ReceivingException;
use App\Models\SalamiProduct;
use App\Models\SalamiReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Services\Salami\ReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class SalamiReceivingServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReceivingService $receiving;

    private StockService $stock;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->receiving = app(ReceivingService::class);
        $this->stock = app(StockService::class);
        $this->user = User::factory()->create();
    }

    public function test_confirming_a_salami_receipt_adds_accepted_quantity_and_persists_actual_snapshots(): void
    {
        $supplier = Supplier::factory()->create(['module_scope' => SupplierScope::Salami]);
        $product = SalamiProduct::factory()->create();
        $this->stock->opening($product, '20.000', $this->user);
        $receipt = $this->draft($supplier, [[
            'product_id' => $product->getKey(),
            'expected_quantity' => '100.000',
            'received_quantity' => '94.000',
            'damaged_quantity' => '0.000',
            'purchase_price' => '81.500',
        ]]);

        $this->assertSame('20.000', $product->fresh()->current_quantity);
        $this->assertDatabaseCount('stock_movements', 1);

        $confirmed = $this->receiving->confirm($receipt, $this->user);
        $item = $confirmed->items->sole();

        $this->assertSame(ReceiptStatus::Confirmed, $confirmed->status);
        $this->assertSame('100.000', $item->expected_quantity);
        $this->assertSame('94.000', $item->received_quantity);
        $this->assertSame('6.000', $item->shortage_quantity);
        $this->assertSame('0.000', $item->surplus_quantity);
        $this->assertSame('94.000', $item->accepted_quantity);
        $this->assertSame('20.000', $item->balance_before);
        $this->assertSame('114.000', $item->balance_after);
        $this->assertSame('114.000', $product->fresh()->current_quantity);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertSame($item->getKey(), $item->stockMovement->reference_id);
    }

    public function test_damaged_quantity_is_excluded_from_stock_when_confirming_a_receipt(): void
    {
        $supplier = Supplier::factory()->create(['module_scope' => SupplierScope::Both]);
        $product = SalamiProduct::factory()->create();
        $receipt = $this->draft($supplier, [[
            'product_id' => $product->getKey(),
            'expected_quantity' => '100.000',
            'received_quantity' => '94.000',
            'damaged_quantity' => '4.000',
        ]]);

        $confirmed = $this->receiving->confirm($receipt, $this->user);
        $item = $confirmed->items->sole();

        $this->assertSame('6.000', $item->shortage_quantity);
        $this->assertSame('90.000', $item->accepted_quantity);
        $this->assertSame('90.000', $product->fresh()->current_quantity);
    }

    public function test_receipt_surplus_is_calculated_without_a_negative_shortage(): void
    {
        $supplier = Supplier::factory()->create();
        $product = SalamiProduct::factory()->create();
        $receipt = $this->draft($supplier, [[
            'product_id' => $product->getKey(),
            'expected_quantity' => '100.000',
            'received_quantity' => '105.000',
            'damaged_quantity' => '0.000',
        ]]);

        $item = $this->receiving->confirm($receipt, $this->user)->items->sole();

        $this->assertSame('0.000', $item->shortage_quantity);
        $this->assertSame('5.000', $item->surplus_quantity);
        $this->assertSame('105.000', $item->accepted_quantity);
    }

    public function test_damaged_quantity_greater_than_received_is_rejected_without_creating_a_draft(): void
    {
        $supplier = Supplier::factory()->create();
        $product = SalamiProduct::factory()->create();

        $this->expectException(InvalidStockQuantityException::class);

        $this->draft($supplier, [[
            'product_id' => $product->getKey(),
            'expected_quantity' => '10.000',
            'received_quantity' => '4.000',
            'damaged_quantity' => '5.000',
        ]]);
    }

    public function test_duplicate_product_in_a_receipt_is_rejected_and_the_draft_is_rolled_back(): void
    {
        $supplier = Supplier::factory()->create();
        $product = SalamiProduct::factory()->create();

        try {
            $this->draft($supplier, [
                ['product_id' => $product->getKey(), 'expected_quantity' => '1.000', 'received_quantity' => '1.000', 'damaged_quantity' => '0.000'],
                ['product_id' => $product->getKey(), 'expected_quantity' => '2.000', 'received_quantity' => '2.000', 'damaged_quantity' => '0.000'],
            ]);
            $this->fail('The same product cannot be selected twice in one receipt.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('salami_receipts', 0);
            $this->assertDatabaseCount('salami_receipt_items', 0);
        }
    }

    public function test_flower_only_supplier_is_rejected_while_a_shared_supplier_is_allowed(): void
    {
        $flowerSupplier = Supplier::factory()->create(['module_scope' => SupplierScope::Flower]);
        $sharedSupplier = Supplier::factory()->create(['module_scope' => SupplierScope::Both]);
        $product = SalamiProduct::factory()->create();
        $items = [['product_id' => $product->getKey(), 'expected_quantity' => '1.000', 'received_quantity' => '1.000', 'damaged_quantity' => '0.000']];

        try {
            $this->draft($flowerSupplier, $items);
            $this->fail('A flower-only supplier must not be used in Salami receiving.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('salami_receipts', 0);
        }

        $receipt = $this->draft($sharedSupplier, $items);

        $this->assertSame($sharedSupplier->getKey(), $receipt->supplier_id);
    }

    public function test_draft_receipts_do_not_change_stock_or_create_movements(): void
    {
        $supplier = Supplier::factory()->create();
        $product = SalamiProduct::factory()->create();

        $receipt = $this->draft($supplier, [[
            'product_id' => $product->getKey(),
            'expected_quantity' => '20.000',
            'received_quantity' => '20.000',
            'damaged_quantity' => '0.000',
        ]]);

        $this->assertSame(ReceiptStatus::Draft, $receipt->status);
        $this->assertSame('0.000', $product->fresh()->current_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_successful_multi_item_receipt_creates_exactly_one_receipt_movement_per_item(): void
    {
        $supplier = Supplier::factory()->create();
        $firstProduct = SalamiProduct::factory()->create();
        $secondProduct = SalamiProduct::factory()->create();
        $receipt = $this->draft($supplier, [
            ['product_id' => $firstProduct->getKey(), 'expected_quantity' => '2.000', 'received_quantity' => '2.000', 'damaged_quantity' => '0.000'],
            ['product_id' => $secondProduct->getKey(), 'expected_quantity' => '3.500', 'received_quantity' => '3.500', 'damaged_quantity' => '0.000'],
        ]);

        $this->receiving->confirm($receipt, $this->user);

        $this->assertSame('2.000', $firstProduct->fresh()->current_quantity);
        $this->assertSame('3.500', $secondProduct->fresh()->current_quantity);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseCount('salami_receipt_items', 2);
    }

    public function test_repeated_confirmation_is_rejected_without_a_second_stock_movement(): void
    {
        $supplier = Supplier::factory()->create();
        $product = SalamiProduct::factory()->create();
        $receipt = $this->draft($supplier, [[
            'product_id' => $product->getKey(),
            'expected_quantity' => '10.000',
            'received_quantity' => '10.000',
            'damaged_quantity' => '0.000',
        ]]);

        $this->receiving->confirm($receipt, $this->user);

        $this->expectException(ReceivingException::class);

        $this->receiving->confirm($receipt, $this->user);
    }

    public function test_multi_item_confirmation_rolls_back_every_stock_change_when_a_later_item_fails(): void
    {
        $supplier = Supplier::factory()->create();
        $firstProduct = SalamiProduct::factory()->create();
        $secondProduct = SalamiProduct::factory()->create();
        $receipt = $this->draft($supplier, [
            ['product_id' => $firstProduct->getKey(), 'expected_quantity' => '20.000', 'received_quantity' => '20.000', 'damaged_quantity' => '0.000'],
            ['product_id' => $secondProduct->getKey(), 'expected_quantity' => '30.000', 'received_quantity' => '30.000', 'damaged_quantity' => '0.000'],
        ]);
        $secondProduct->update(['is_active' => false]);

        try {
            $this->receiving->confirm($receipt, $this->user);
            $this->fail('Confirmation must roll back when an item cannot be processed.');
        } catch (ReceivingException) {
            $this->assertSame('0.000', $firstProduct->fresh()->current_quantity);
            $this->assertSame('0.000', $secondProduct->fresh()->current_quantity);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertSame(ReceiptStatus::Draft, $receipt->fresh()->status);
        }
    }

    public function test_confirmed_receipts_and_their_items_cannot_be_edited_or_deleted(): void
    {
        $supplier = Supplier::factory()->create();
        $product = SalamiProduct::factory()->create();
        $receipt = $this->draft($supplier, [[
            'product_id' => $product->getKey(),
            'expected_quantity' => '1.000',
            'received_quantity' => '1.000',
            'damaged_quantity' => '0.000',
        ]]);
        $confirmed = $this->receiving->confirm($receipt, $this->user);

        try {
            $this->receiving->updateDraft($confirmed, $this->draftAttributes($supplier), [], $this->user);
            $this->fail('A confirmed receipt cannot return to draft editing.');
        } catch (ReceivingException) {
            // The service rejects the edit before it can write changes.
        }

        try {
            $confirmed->items->sole()->update(['received_quantity' => '2.000']);
            $this->fail('A confirmed receipt item must be immutable.');
        } catch (LogicException) {
            // The model protects direct application-level item writes too.
        }

        $this->expectException(LogicException::class);

        $confirmed->delete();
    }

    public function test_opening_stock_cannot_be_recorded_after_any_previous_stock_history_exists(): void
    {
        $product = SalamiProduct::factory()->create();
        $this->stock->receipt($product, '1.000', $this->user);
        $this->stock->sale($product, '1.000', $this->user);

        $this->assertSame('0.000', $product->fresh()->current_quantity);
        $this->expectException(StockMutationException::class);

        $this->stock->opening($product, '20.000', $this->user);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function draft(Supplier $supplier, array $items): SalamiReceipt
    {
        return $this->receiving->createDraft($this->draftAttributes($supplier), $items, $this->user);
    }

    /**
     * @return array{supplier_id: int, supplier_invoice_number: string, receipt_date: string, notes: string}
     */
    private function draftAttributes(Supplier $supplier): array
    {
        return [
            'supplier_id' => $supplier->getKey(),
            'supplier_invoice_number' => 'SUP-123',
            'receipt_date' => '2026-08-20',
            'notes' => 'استلام تجريبي',
        ];
    }
}

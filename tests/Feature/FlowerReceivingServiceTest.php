<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\ReceiptStatus;
use App\Enums\SupplierScope;
use App\Exceptions\Flowers\ReceivingException;
use App\Exceptions\Inventory\InvalidStockQuantityException;
use App\Exceptions\Inventory\StockMutationException;
use App\Models\FlowerProduct;
use App\Models\FlowerReceipt;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Flowers\ReceivingService;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class FlowerReceivingServiceTest extends TestCase
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

    public function test_opening_and_receiving_add_only_accepted_flower_quantity_and_store_snapshots(): void
    {
        $supplier = Supplier::factory()->create(['module_scope' => SupplierScope::Flower]);
        $product = FlowerProduct::factory()->create(['name' => 'جوري', 'color' => 'أحمر']);
        $this->stock->opening($product, '100.000', $this->user);
        $receipt = $this->draft($supplier, [[
            'flower_product_id' => $product->getKey(),
            'expected_quantity' => '500.000',
            'received_quantity' => '480.000',
            'damaged_quantity' => '30.000',
            'purchase_price' => '15.125',
        ]]);

        $confirmed = $this->receiving->confirm($receipt, $this->user);
        $item = $confirmed->items->sole();

        $this->assertSame(ReceiptStatus::Confirmed, $confirmed->status);
        $this->assertSame('20.000', $item->shortage_quantity);
        $this->assertSame('0.000', $item->surplus_quantity);
        $this->assertSame('30.000', $item->damaged_quantity);
        $this->assertSame('450.000', $item->accepted_quantity);
        $this->assertSame('100.000', $item->balance_before);
        $this->assertSame('550.000', $item->balance_after);
        $this->assertSame('أحمر', $item->color);
        $this->assertSame('550.000', $product->fresh()->current_quantity);
        $this->assertSame(2, StockMovement::query()->count());
        $this->assertSame('450.000', $item->stockMovement->quantity);
    }

    public function test_surplus_is_recorded_without_a_negative_shortage(): void
    {
        $supplier = Supplier::factory()->create(['module_scope' => SupplierScope::Both]);
        $product = FlowerProduct::factory()->create();
        $receipt = $this->draft($supplier, [[
            'flower_product_id' => $product->getKey(),
            'expected_quantity' => '500.000',
            'received_quantity' => '520.000',
            'damaged_quantity' => '0.000',
        ]]);

        $item = $this->receiving->confirm($receipt, $this->user)->items->sole();

        $this->assertSame('0.000', $item->shortage_quantity);
        $this->assertSame('20.000', $item->surplus_quantity);
        $this->assertSame('520.000', $item->accepted_quantity);
        $this->assertSame('520.000', $product->fresh()->current_quantity);
    }

    public function test_receiving_validation_rejects_damaged_quantity_above_received_and_duplicate_products(): void
    {
        $supplier = Supplier::factory()->create();
        $product = FlowerProduct::factory()->create();

        $this->expectException(InvalidStockQuantityException::class);

        try {
            $this->draft($supplier, [[
                'flower_product_id' => $product->getKey(),
                'expected_quantity' => '10.000',
                'received_quantity' => '5.000',
                'damaged_quantity' => '6.000',
            ]]);
        } finally {
            $this->assertDatabaseCount('flower_receipts', 0);
        }
    }

    public function test_duplicate_flower_product_is_rejected_in_the_same_receipt(): void
    {
        $supplier = Supplier::factory()->create();
        $product = FlowerProduct::factory()->create();

        try {
            $this->draft($supplier, [
                ['flower_product_id' => $product->getKey(), 'expected_quantity' => '1.000', 'received_quantity' => '1.000', 'damaged_quantity' => '0.000'],
                ['flower_product_id' => $product->getKey(), 'expected_quantity' => '2.000', 'received_quantity' => '2.000', 'damaged_quantity' => '0.000'],
            ]);
            $this->fail('Duplicate flower products must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('flower_receipts', 0);
        }
    }

    public function test_flower_supplier_scope_and_draft_receipts_are_enforced(): void
    {
        $flowerOnly = Supplier::factory()->create(['module_scope' => SupplierScope::Flower]);
        $salamiOnly = Supplier::factory()->create(['module_scope' => SupplierScope::Salami]);
        $product = FlowerProduct::factory()->create();
        $items = [['flower_product_id' => $product->getKey(), 'expected_quantity' => '10.000', 'received_quantity' => '10.000', 'damaged_quantity' => '0.000']];

        try {
            $this->draft($salamiOnly, $items);
            $this->fail('Salami-only suppliers cannot be used for Flower receipts.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('flower_receipts', 0);
        }

        $draft = $this->draft($flowerOnly, $items);

        $this->assertSame(ReceiptStatus::Draft, $draft->status);
        $this->assertSame('0.000', $product->fresh()->current_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_confirmed_receipt_is_idempotent_immutable_and_creates_one_movement_per_item(): void
    {
        $supplier = Supplier::factory()->create();
        $first = FlowerProduct::factory()->create();
        $second = FlowerProduct::factory()->create();
        $receipt = $this->draft($supplier, [
            ['flower_product_id' => $first->getKey(), 'expected_quantity' => '2.000', 'received_quantity' => '2.000', 'damaged_quantity' => '0.000'],
            ['flower_product_id' => $second->getKey(), 'expected_quantity' => '3.500', 'received_quantity' => '3.500', 'damaged_quantity' => '0.000'],
        ]);
        $confirmed = $this->receiving->confirm($receipt, $this->user);

        $this->assertSame(2, StockMovement::query()->where('movement_type', MovementType::Receipt->value)->count());

        try {
            $this->receiving->confirm($receipt, $this->user);
            $this->fail('A confirmed receipt must not be processed twice.');
        } catch (ReceivingException) {
            $this->assertSame('2.000', $first->fresh()->current_quantity);
            $this->assertSame('3.500', $second->fresh()->current_quantity);
        }

        try {
            $confirmed->items->first()->update(['received_quantity' => '3.000']);
            $this->fail('Confirmed receipt items must be immutable.');
        } catch (LogicException) {
            // The model prevents direct item mutation after confirmation.
        }

        try {
            $confirmed->items()->create([
                'flower_product_id' => $first->getKey(),
                'product_name' => $first->name,
                'unit' => $first->unit,
                'expected_quantity' => '1.000',
                'received_quantity' => '1.000',
                'damaged_quantity' => '0.000',
                'shortage_quantity' => '0.000',
                'surplus_quantity' => '0.000',
                'accepted_quantity' => '1.000',
            ]);
            $this->fail('New items cannot be added to a confirmed receipt.');
        } catch (LogicException) {
            // The model prevents direct item creation after confirmation.
        }

        $this->expectException(LogicException::class);

        $confirmed->delete();
    }

    public function test_multi_item_receiving_rolls_back_all_flower_stock_changes_when_one_item_fails(): void
    {
        $supplier = Supplier::factory()->create();
        $first = FlowerProduct::factory()->create();
        $second = FlowerProduct::factory()->create();
        $receipt = $this->draft($supplier, [
            ['flower_product_id' => $first->getKey(), 'expected_quantity' => '20.000', 'received_quantity' => '20.000', 'damaged_quantity' => '0.000'],
            ['flower_product_id' => $second->getKey(), 'expected_quantity' => '30.000', 'received_quantity' => '30.000', 'damaged_quantity' => '0.000'],
        ]);
        $second->update(['is_active' => false]);

        try {
            $this->receiving->confirm($receipt, $this->user);
            $this->fail('All product changes must roll back when a later item cannot confirm.');
        } catch (ReceivingException) {
            $this->assertSame('0.000', $first->fresh()->current_quantity);
            $this->assertSame('0.000', $second->fresh()->current_quantity);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertSame(ReceiptStatus::Draft, $receipt->fresh()->status);
        }
    }

    public function test_flower_opening_cannot_be_repeated_after_stock_history_exists(): void
    {
        $product = FlowerProduct::factory()->create();
        $this->stock->opening($product, '100.000', $this->user);

        $this->expectException(StockMutationException::class);

        $this->stock->opening($product, '10.000', $this->user);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function draft(Supplier $supplier, array $items): FlowerReceipt
    {
        return $this->receiving->createDraft([
            'supplier_id' => $supplier->getKey(),
            'supplier_invoice_number' => 'FL-SUP-001',
            'receipt_date' => '2026-08-20',
            'notes' => 'استلام ورد تجريبي',
        ], $items, $this->user);
    }
}

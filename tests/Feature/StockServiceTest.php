<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Exceptions\Inventory\DuplicateStockMutationException;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\FlowerProduct;
use App\Models\SalamiProduct;
use App\Models\SalamiReceipt;
use App\Models\SalamiReceiptItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use App\Services\Inventory\StockService;
use App\Support\Quantity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = app(StockService::class);
        $this->user = User::factory()->create();
    }

    public function test_salami_lifecycle_records_consistent_signed_movements(): void
    {
        $product = SalamiProduct::factory()->create();

        $opening = $this->stock->opening($product, '20.000', $this->user, 'رصيد افتتاحي');
        $receipt = $this->stock->receipt($product, '94.000', $this->user);
        $sale = $this->stock->sale($product, '10.000', $this->user);
        $waste = $this->stock->waste($product, '4.000', $this->user);
        $adjustmentIn = $this->stock->adjust($product, '5.000', $this->user);
        $adjustmentOut = $this->stock->adjust($product, '-2.000', $this->user);

        $product->refresh();

        $this->assertSame('103.000', $product->current_quantity);
        $this->assertMovement($opening, MovementType::Opening, '20.000', '0.000', '20.000');
        $this->assertMovement($receipt, MovementType::Receipt, '94.000', '20.000', '114.000');
        $this->assertMovement($sale, MovementType::Sale, '-10.000', '114.000', '104.000');
        $this->assertMovement($waste, MovementType::Waste, '-4.000', '104.000', '100.000');
        $this->assertMovement($adjustmentIn, MovementType::AdjustmentIn, '5.000', '100.000', '105.000');
        $this->assertMovement($adjustmentOut, MovementType::AdjustmentOut, '-2.000', '105.000', '103.000');

        $this->assertDatabaseCount('stock_movements', 6);
        $this->assertDatabaseHas('stock_openings', [
            'stockable_type' => 'salami_product',
            'stockable_id' => $product->getKey(),
            'quantity' => '20.000',
            'created_by' => $this->user->getKey(),
        ]);
    }

    public function test_flower_opening_uses_the_same_engine_with_its_own_morph_alias(): void
    {
        $product = FlowerProduct::factory()->create();

        $opening = $this->stock->opening($product, '100.000', $this->user);

        $product->refresh();

        $this->assertSame('100.000', $product->current_quantity);
        $this->assertMovement($opening, MovementType::Opening, '100.000', '0.000', '100.000');
        $this->assertSame('flower_product', $opening->getRawOriginal('stockable_type'));
        $this->assertSame($product->getKey(), $opening->stockable_id);
    }

    public function test_insufficient_stock_rejects_the_mutation_without_changing_the_balance_or_ledger(): void
    {
        $product = SalamiProduct::factory()->create();
        $this->stock->opening($product, '8.000', $this->user);

        try {
            $this->stock->sale($product, '10.000', $this->user);
            $this->fail('The sale should have been rejected because stock is insufficient.');
        } catch (InsufficientStockException) {
            // The exception is the expected domain outcome.
        }

        $product->refresh();

        $this->assertSame('8.000', $product->current_quantity);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_a_movement_creation_failure_rolls_back_the_product_update(): void
    {
        $product = SalamiProduct::factory()->create();
        $this->stock->opening($product, '20.000', $this->user);

        $failingStock = new StockService(new class extends StockMovementService
        {
            public function record(array $attributes): StockMovement
            {
                throw new RuntimeException('Simulated ledger write failure.');
            }
        });

        try {
            $failingStock->receipt($product, '94.000', $this->user);
            $this->fail('The simulated ledger failure should have been propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated ledger write failure.', $exception->getMessage());
        }

        $product->refresh();

        $this->assertSame('20.000', $product->current_quantity);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_reversal_uses_the_current_balance_and_cannot_be_created_twice(): void
    {
        $product = SalamiProduct::factory()->create();
        $this->stock->opening($product, '100.000', $this->user);
        $sale = $this->stock->sale($product, '10.000', $this->user);

        $reversal = $this->stock->reverse($sale, $this->user, 'إلغاء البيع');

        $product->refresh();

        $this->assertSame('100.000', $product->current_quantity);
        $this->assertMovement($reversal, MovementType::Reversal, '10.000', '90.000', '100.000');
        $this->assertSame($sale->getKey(), $reversal->reverses_movement_id);

        try {
            $this->stock->reverse($sale, $this->user);
            $this->fail('A movement must not be reversed more than once.');
        } catch (DuplicateStockMutationException) {
            // The original reversal remains the one immutable reversal record.
        }

        $this->assertDatabaseCount('stock_movements', 3);
    }

    public function test_decimal_quantities_are_calculated_without_floating_point_rounding(): void
    {
        $product = SalamiProduct::factory()->create();

        $this->stock->opening($product, '0.100', $this->user);
        $receipt = $this->stock->receipt($product, '0.200', $this->user);
        $sale = $this->stock->sale($product, '0.250', $this->user);

        $product->refresh();

        $this->assertMovement($receipt, MovementType::Receipt, '0.200', '0.100', '0.300');
        $this->assertMovement($sale, MovementType::Sale, '-0.250', '0.300', '0.050');
        $this->assertSame('0.050', $product->current_quantity);
    }

    public function test_salami_and_flower_with_the_same_numeric_id_do_not_share_movements(): void
    {
        $salamiProduct = SalamiProduct::factory()->create();
        $flowerProduct = FlowerProduct::factory()->create();

        $this->assertSame($salamiProduct->getKey(), $flowerProduct->getKey());

        $salamiOpening = $this->stock->opening($salamiProduct, '20.000', $this->user);
        $flowerOpening = $this->stock->opening($flowerProduct, '100.000', $this->user);

        $this->assertSame('salami_product', $salamiOpening->getRawOriginal('stockable_type'));
        $this->assertSame('flower_product', $flowerOpening->getRawOriginal('stockable_type'));
        $this->assertSame(1, StockMovement::query()->where('stockable_type', 'salami_product')->where('stockable_id', $salamiProduct->getKey())->count());
        $this->assertSame(1, StockMovement::query()->where('stockable_type', 'flower_product')->where('stockable_id', $flowerProduct->getKey())->count());
    }

    public function test_a_document_item_can_only_create_one_movement_of_each_type(): void
    {
        $product = SalamiProduct::factory()->create();
        $receipt = SalamiReceipt::factory()->create(['created_by' => $this->user->getKey()]);
        $receiptItem = SalamiReceiptItem::factory()->create([
            'receipt_id' => $receipt->getKey(),
            'product_id' => $product->getKey(),
        ]);

        $movement = $this->stock->receipt($product, '20.000', $this->user, $receiptItem);

        try {
            $this->stock->receipt($product, '20.000', $this->user, $receiptItem);
            $this->fail('The same receipt item must not increase stock twice.');
        } catch (DuplicateStockMutationException) {
            // The existing movement is the idempotent result of the first confirmation.
        }

        $product->refresh();

        $this->assertSame('20.000', $product->current_quantity);
        $this->assertSame('salami_receipt_item', $movement->getRawOriginal('reference_type'));
        $this->assertSame($receiptItem->getKey(), $movement->reference_id);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_stock_movements_are_immutable_after_recording(): void
    {
        $product = SalamiProduct::factory()->create();
        $movement = $this->stock->opening($product, '20.000', $this->user);

        $this->expectException(LogicException::class);

        $movement->update(['notes' => 'غير مسموح']);
    }

    private function assertMovement(
        StockMovement $movement,
        MovementType $type,
        string $quantity,
        string $balanceBefore,
        string $balanceAfter,
    ): void {
        $this->assertSame($type, $movement->movement_type);
        $this->assertSame($quantity, $movement->quantity);
        $this->assertSame($balanceBefore, $movement->balance_before);
        $this->assertSame($balanceAfter, $movement->balance_after);

        $calculatedBalance = Quantity::from($movement->balance_before)
            ->plus(Quantity::from($movement->quantity))
            ->toString();

        $this->assertSame($movement->balance_after, $calculatedBalance);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Exceptions\Salami\StockOperationException;
use App\Models\SalamiProduct;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use App\Services\Inventory\StockService;
use App\Services\Salami\AdjustmentService;
use App\Services\Salami\WasteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class SalamiStockOperationsServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdjustmentService $adjustments;

    private StockService $stock;

    private User $user;

    private WasteService $waste;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adjustments = app(AdjustmentService::class);
        $this->stock = app(StockService::class);
        $this->user = User::factory()->create();
        $this->waste = app(WasteService::class);
    }

    public function test_waste_deducts_stock_and_creates_one_immutable_record_and_movement(): void
    {
        $product = SalamiProduct::factory()->create();
        $this->stock->opening($product, '104.000', $this->user);

        $waste = $this->waste->record([
            'product_id' => $product->getKey(),
            'quantity' => '4.000',
            'reason' => 'تالف أثناء التخزين',
            'waste_date' => '2026-08-20',
            'notes' => 'اختبار',
        ], $this->user);

        $this->assertSame('100.000', $product->fresh()->current_quantity);
        $this->assertSame('4.000', $waste->quantity);
        $this->assertSame($this->user->getKey(), $waste->confirmed_by);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => MovementType::Waste->value,
            'quantity' => '-4.000',
            'balance_before' => '104.000',
            'balance_after' => '100.000',
            'reference_type' => 'stock_waste',
            'reference_id' => $waste->getKey(),
        ]);

        $this->expectException(LogicException::class);

        $waste->update(['reason' => 'تعديل غير مسموح']);
    }

    public function test_waste_greater_than_available_is_rejected_without_creating_a_record(): void
    {
        $product = SalamiProduct::factory()->create();
        $this->stock->opening($product, '8.000', $this->user);

        $this->expectException(StockOperationException::class);

        try {
            $this->waste->record([
                'product_id' => $product->getKey(),
                'quantity' => '10.000',
                'reason' => 'تالف',
                'waste_date' => '2026-08-20',
            ], $this->user);
        } finally {
            $this->assertSame('8.000', $product->fresh()->current_quantity);
            $this->assertDatabaseCount('stock_wastes', 0);
            $this->assertSame(1, StockMovement::query()->count());
        }
    }

    public function test_waste_document_and_stock_update_roll_back_together_when_ledger_writing_fails(): void
    {
        $product = SalamiProduct::factory()->create();
        $this->stock->opening($product, '104.000', $this->user);
        $failingWaste = new WasteService(new StockService(new class extends StockMovementService
        {
            public function record(array $attributes): StockMovement
            {
                throw new RuntimeException('Simulated movement failure.');
            }
        }));

        $this->expectException(RuntimeException::class);

        try {
            $failingWaste->record([
                'product_id' => $product->getKey(),
                'quantity' => '4.000',
                'reason' => 'تالف',
                'waste_date' => '2026-08-20',
            ], $this->user);
        } finally {
            $this->assertSame('104.000', $product->fresh()->current_quantity);
            $this->assertDatabaseCount('stock_wastes', 0);
            $this->assertSame(1, StockMovement::query()->count());
        }
    }

    public function test_adjustment_out_uses_the_rechecked_system_balance_and_records_the_difference(): void
    {
        $product = SalamiProduct::factory()->create();
        $this->stock->opening($product, '111.000', $this->user);

        $adjustment = $this->adjustments->record([
            'product_id' => $product->getKey(),
            'actual_quantity' => '109.000',
            'adjustment_date' => '2026-08-20',
            'reason' => 'جرد فعلي',
            'notes' => 'فرق بالجرد',
        ], $this->user);

        $this->assertSame('109.000', $product->fresh()->current_quantity);
        $this->assertSame('111.000', $adjustment->system_quantity);
        $this->assertSame('-2.000', $adjustment->difference_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => MovementType::AdjustmentOut->value,
            'quantity' => '-2.000',
            'balance_before' => '111.000',
            'balance_after' => '109.000',
            'reference_type' => 'stock_adjustment',
            'reference_id' => $adjustment->getKey(),
        ]);
    }

    public function test_adjustment_in_increases_stock_and_zero_difference_creates_nothing(): void
    {
        $product = SalamiProduct::factory()->create();
        $this->stock->opening($product, '100.000', $this->user);

        $adjustment = $this->adjustments->record([
            'product_id' => $product->getKey(),
            'actual_quantity' => '105.000',
            'adjustment_date' => '2026-08-20',
            'reason' => 'جرد فعلي',
        ], $this->user);

        $this->assertSame('105.000', $product->fresh()->current_quantity);
        $this->assertSame('5.000', $adjustment->difference_quantity);
        $this->assertSame(1, StockMovement::query()->where('movement_type', MovementType::AdjustmentIn->value)->count());

        try {
            $this->adjustments->record([
                'product_id' => $product->getKey(),
                'actual_quantity' => '105.000',
                'adjustment_date' => '2026-08-20',
                'reason' => 'لا فرق',
            ], $this->user);
            $this->fail('Zero differences should not create meaningless adjustments.');
        } catch (StockOperationException) {
            $this->assertDatabaseCount('stock_adjustments', 1);
            $this->assertSame(2, StockMovement::query()->count());
        }
    }

    public function test_adjustment_rechecks_the_latest_balance_inside_its_transaction(): void
    {
        $product = SalamiProduct::factory()->create();
        $this->stock->opening($product, '111.000', $this->user);
        $this->stock->receipt($product, '1.000', $this->user);

        $adjustment = $this->adjustments->record([
            'product_id' => $product->getKey(),
            'actual_quantity' => '109.000',
            'adjustment_date' => '2026-08-20',
            'reason' => 'جرد بعد حركة لاحقة',
        ], $this->user);

        $this->assertSame('112.000', $adjustment->system_quantity);
        $this->assertSame('-3.000', $adjustment->difference_quantity);
        $this->assertSame('109.000', $product->fresh()->current_quantity);
    }
}

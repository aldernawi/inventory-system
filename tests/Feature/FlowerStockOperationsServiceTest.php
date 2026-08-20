<?php

namespace Tests\Feature;

use App\Enums\FlowerExitType;
use App\Enums\MovementType;
use App\Exceptions\Flowers\StockOperationException;
use App\Models\FlowerProduct;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Flowers\ExitService;
use App\Services\Flowers\WasteService;
use App\Services\Inventory\StockMovementService;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class FlowerStockOperationsServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExitService $exits;

    private StockService $stock;

    private User $user;

    private WasteService $waste;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exits = app(ExitService::class);
        $this->stock = app(StockService::class);
        $this->user = User::factory()->create();
        $this->waste = app(WasteService::class);
    }

    public function test_flower_waste_deducts_stock_and_is_immutable(): void
    {
        $product = FlowerProduct::factory()->create();
        $this->stock->opening($product, '550.000', $this->user);

        $waste = $this->waste->record([
            'flower_product_id' => $product->getKey(),
            'quantity' => '15.000',
            'reason' => 'ذبول',
            'waste_date' => '2026-08-20',
        ], $this->user);

        $this->assertSame('535.000', $product->fresh()->current_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => MovementType::Waste->value,
            'quantity' => '-15.000',
            'reference_type' => 'stock_waste',
            'reference_id' => $waste->getKey(),
        ]);

        $this->expectException(LogicException::class);

        $waste->update(['reason' => 'تعديل غير مسموح']);
    }

    public function test_flower_waste_rejects_insufficient_stock_and_rolls_back_with_a_failed_ledger_write(): void
    {
        $product = FlowerProduct::factory()->create();
        $this->stock->opening($product, '10.000', $this->user);

        try {
            $this->waste->record([
                'flower_product_id' => $product->getKey(),
                'quantity' => '11.000',
                'reason' => 'كسر',
                'waste_date' => '2026-08-20',
            ], $this->user);
            $this->fail('Waste cannot exceed the available balance.');
        } catch (StockOperationException) {
            $this->assertSame('10.000', $product->fresh()->current_quantity);
            $this->assertDatabaseCount('stock_wastes', 0);
        }

        $failingWaste = new WasteService(new StockService(new class extends StockMovementService
        {
            public function record(array $attributes): StockMovement
            {
                throw new RuntimeException('Simulated flower ledger failure.');
            }
        }));

        try {
            $failingWaste->record([
                'flower_product_id' => $product->getKey(),
                'quantity' => '1.000',
                'reason' => 'حرارة',
                'waste_date' => '2026-08-20',
            ], $this->user);
            $this->fail('A failing ledger write must roll back the waste document.');
        } catch (RuntimeException) {
            $this->assertSame('10.000', $product->fresh()->current_quantity);
            $this->assertDatabaseCount('stock_wastes', 0);
        }
    }

    public function test_manual_flower_exit_deducts_stock_with_a_plain_text_recipient_and_is_immutable(): void
    {
        $product = FlowerProduct::factory()->create();
        $this->stock->opening($product, '535.000', $this->user);

        $exit = $this->exits->record([
            'flower_product_id' => $product->getKey(),
            'quantity' => '20.000',
            'exit_date' => '2026-08-20',
            'exit_type' => FlowerExitType::Gift->value,
            'recipient_name' => 'جمعية الأمل',
            'notes' => 'هدية',
        ], $this->user);

        $this->assertSame('515.000', $product->fresh()->current_quantity);
        $this->assertSame('جمعية الأمل', $exit->recipient_name);
        $this->assertFalse(method_exists($exit, 'customer'));
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => MovementType::ManualExit->value,
            'quantity' => '-20.000',
            'reference_type' => 'flower_exit',
            'reference_id' => $exit->getKey(),
        ]);

        $this->expectException(LogicException::class);

        $exit->update(['recipient_name' => 'تعديل غير مسموح']);
    }

    public function test_manual_exit_rejects_insufficient_stock_and_rolls_back_when_ledger_write_fails(): void
    {
        $product = FlowerProduct::factory()->create();
        $this->stock->opening($product, '5.000', $this->user);

        try {
            $this->exits->record([
                'flower_product_id' => $product->getKey(),
                'quantity' => '6.000',
                'exit_date' => '2026-08-20',
                'exit_type' => FlowerExitType::Sample->value,
            ], $this->user);
            $this->fail('Manual exits cannot exceed stock.');
        } catch (StockOperationException) {
            $this->assertSame('5.000', $product->fresh()->current_quantity);
            $this->assertDatabaseCount('flower_exits', 0);
        }

        $failingExits = new ExitService(new StockService(new class extends StockMovementService
        {
            public function record(array $attributes): StockMovement
            {
                throw new RuntimeException('Simulated flower exit ledger failure.');
            }
        }));

        try {
            $failingExits->record([
                'flower_product_id' => $product->getKey(),
                'quantity' => '1.000',
                'exit_date' => '2026-08-20',
                'exit_type' => FlowerExitType::Other->value,
            ], $this->user);
            $this->fail('A failed ledger write must roll back the exit record.');
        } catch (RuntimeException) {
            $this->assertSame('5.000', $product->fresh()->current_quantity);
            $this->assertDatabaseCount('flower_exits', 0);
        }
    }
}

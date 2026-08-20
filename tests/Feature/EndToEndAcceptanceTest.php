<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\SupplierScope;
use App\Models\FlowerProduct;
use App\Models\SalamiCustomer;
use App\Models\SalamiProduct;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Flowers\ExitService as FlowerExitService;
use App\Services\Flowers\InvoiceService as FlowerInvoiceService;
use App\Services\Flowers\ReceivingService as FlowerReceivingService;
use App\Services\Flowers\WasteService as FlowerWasteService;
use App\Services\Inventory\StockLedgerReconciler;
use App\Services\Inventory\StockService;
use App\Services\Salami\AdjustmentService;
use App\Services\Salami\InvoiceService as SalamiInvoiceService;
use App\Services\Salami\ReceivingService as SalamiReceivingService;
use App\Services\Salami\WasteService as SalamiWasteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndToEndAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_salami_acceptance_workflow_preserves_auditable_balances(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['module_scope' => SupplierScope::Salami]);
        $product = SalamiProduct::factory()->create(['name' => 'سلامي نوع A', 'sale_price' => '100.000']);
        $customer = SalamiCustomer::factory()->create();
        $stock = app(StockService::class);
        $stock->opening($product, '20.000', $user);

        $receipt = app(SalamiReceivingService::class)->createDraft(
            ['supplier_id' => $supplier->id, 'receipt_date' => '2026-08-20'],
            [['product_id' => $product->id, 'expected_quantity' => '100.000', 'received_quantity' => '94.000', 'damaged_quantity' => '0.000']],
            $user,
        );
        $confirmedReceipt = app(SalamiReceivingService::class)->confirm($receipt, $user);
        $this->assertSame('6.000', $confirmedReceipt->items->sole()->shortage_quantity);
        $this->assertSame('94.000', $confirmedReceipt->items->sole()->accepted_quantity);
        $this->assertSame('114.000', $product->fresh()->current_quantity);

        $invoice = app(SalamiInvoiceService::class)->createDraft(
            ['customer_id' => $customer->id, 'invoice_date' => '2026-08-20', 'payment_type' => 'cash', 'discount_amount' => '0.000', 'paid_amount' => '1000.000'],
            [['product_id' => $product->id, 'quantity' => '10.000', 'unit_price' => '100.000']],
            $user,
        );
        $invoice = app(SalamiInvoiceService::class)->confirm($invoice, $user);
        $this->assertSame('104.000', $product->fresh()->current_quantity);

        app(SalamiWasteService::class)->record(['product_id' => $product->id, 'quantity' => '4.000', 'reason' => 'تالف اختبار', 'waste_date' => '2026-08-20'], $user);
        $this->assertSame('100.000', $product->fresh()->current_quantity);

        app(AdjustmentService::class)->record(['product_id' => $product->id, 'actual_quantity' => '98.000', 'adjustment_date' => '2026-08-20', 'reason' => 'جرد فعلي'], $user);
        $this->assertSame('98.000', $product->fresh()->current_quantity);

        app(SalamiInvoiceService::class)->cancel($invoice, $user, 'اختبار قبول');
        $this->assertSame('108.000', $product->fresh()->current_quantity);
        $this->assertSame(
            [MovementType::Opening, MovementType::Receipt, MovementType::Sale, MovementType::Waste, MovementType::AdjustmentOut, MovementType::Reversal],
            StockMovement::query()->where('stockable_type', 'salami_product')->orderBy('id')->pluck('movement_type')->all(),
        );
        $this->assertSame(['20.000', '114.000', '104.000', '100.000', '98.000', '108.000'], StockMovement::query()->where('stockable_type', 'salami_product')->orderBy('id')->pluck('balance_after')->all());
        $this->assertTrue(app(StockLedgerReconciler::class)->check($product->fresh())['matches']);
    }

    public function test_flower_acceptance_workflow_preserves_separation_and_auditable_balances(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['module_scope' => SupplierScope::Flower]);
        $product = FlowerProduct::factory()->create(['name' => 'جوري أحمر', 'color' => 'أحمر', 'sale_price' => '22.000']);
        $stock = app(StockService::class);
        $stock->opening($product, '100.000', $user);

        $receipt = app(FlowerReceivingService::class)->createDraft(
            ['supplier_id' => $supplier->id, 'receipt_date' => '2026-08-20'],
            [['flower_product_id' => $product->id, 'expected_quantity' => '500.000', 'received_quantity' => '480.000', 'damaged_quantity' => '30.000']],
            $user,
        );
        $confirmedReceipt = app(FlowerReceivingService::class)->confirm($receipt, $user);
        $this->assertSame('20.000', $confirmedReceipt->items->sole()->shortage_quantity);
        $this->assertSame('450.000', $confirmedReceipt->items->sole()->accepted_quantity);
        $this->assertSame('550.000', $product->fresh()->current_quantity);

        app(FlowerWasteService::class)->record(['flower_product_id' => $product->id, 'quantity' => '15.000', 'reason' => 'ذبول', 'waste_date' => '2026-08-20'], $user);
        app(FlowerExitService::class)->record(['flower_product_id' => $product->id, 'quantity' => '20.000', 'exit_date' => '2026-08-20', 'exit_type' => 'gift', 'recipient_name' => 'قاعة الربيع'], $user);
        $this->assertSame('515.000', $product->fresh()->current_quantity);

        $invoice = app(FlowerInvoiceService::class)->createDraft(
            ['recipient_name' => 'مستلم نصي فقط', 'invoice_date' => '2026-08-20', 'payment_type' => 'credit', 'discount_amount' => '0.000', 'paid_amount' => '0.000'],
            [['flower_product_id' => $product->id, 'quantity' => '100.000', 'unit_price' => '22.000']],
            $user,
        );
        $invoice = app(FlowerInvoiceService::class)->confirm($invoice, $user);
        $this->assertSame('415.000', $product->fresh()->current_quantity);
        app(FlowerInvoiceService::class)->cancel($invoice, $user, 'اختبار قبول');
        $this->assertSame('515.000', $product->fresh()->current_quantity);
        $this->assertFalse(method_exists($invoice, 'customer'));
        $this->assertSame('مستلم نصي فقط', $invoice->recipient_name);
        $this->assertSame(
            [MovementType::Opening, MovementType::Receipt, MovementType::Waste, MovementType::ManualExit, MovementType::Sale, MovementType::Reversal],
            StockMovement::query()->where('stockable_type', 'flower_product')->orderBy('id')->pluck('movement_type')->all(),
        );
        $this->assertTrue(app(StockLedgerReconciler::class)->check($product->fresh())['matches']);
    }

    public function test_ledger_reconciler_checks_both_product_modules_without_primary_key_collisions(): void
    {
        $user = User::factory()->create();
        $salami = SalamiProduct::factory()->create();
        $flower = FlowerProduct::factory()->create();
        app(StockService::class)->opening($salami, '3.000', $user);
        app(StockService::class)->opening($flower, '7.000', $user);

        $results = app(StockLedgerReconciler::class)->checkAll();

        $this->assertSame($salami->id, $flower->id);
        $this->assertSame([
            ['module' => 'salami', 'product_id' => $salami->id, 'current_quantity' => '3.000', 'ledger_quantity' => '3.000', 'matches' => true],
            ['module' => 'flowers', 'product_id' => $flower->id, 'current_quantity' => '7.000', 'ledger_quantity' => '7.000', 'matches' => true],
        ], $results);
    }
}

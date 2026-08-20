<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\FlowerInvoice;
use App\Models\FlowerInvoiceItem;
use App\Models\FlowerProduct;
use App\Models\FlowerReceipt;
use App\Models\FlowerReceiptItem;
use App\Models\SalamiCustomer;
use App\Models\SalamiInvoice;
use App\Models\SalamiInvoiceItem;
use App\Models\SalamiProduct;
use App\Models\SalamiReceipt;
use App\Models\SalamiReceiptItem;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockOpening;
use App\Models\StockWaste;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stockable_records_use_fixed_aliases_and_keep_matching_numeric_ids_separate(): void
    {
        $salamiProduct = SalamiProduct::factory()->create(['current_quantity' => '5.250']);
        $flowerProduct = FlowerProduct::factory()->create(['current_quantity' => '8.500']);
        $user = User::factory()->create();

        $salamiMovement = StockMovement::factory()->create([
            'stockable_type' => $salamiProduct->getMorphClass(),
            'stockable_id' => $salamiProduct->getKey(),
            'movement_type' => MovementType::Opening,
            'quantity' => '5.250',
            'balance_before' => '0.000',
            'balance_after' => '5.250',
            'created_by' => $user->getKey(),
        ]);

        $flowerMovement = StockMovement::factory()->create([
            'stockable_type' => $flowerProduct->getMorphClass(),
            'stockable_id' => $flowerProduct->getKey(),
            'movement_type' => MovementType::Opening,
            'quantity' => '8.500',
            'balance_before' => '0.000',
            'balance_after' => '8.500',
            'created_by' => $user->getKey(),
        ]);

        $this->assertSame('salami_product', $salamiMovement->getRawOriginal('stockable_type'));
        $this->assertSame('flower_product', $flowerMovement->getRawOriginal('stockable_type'));
        $this->assertSame(SalamiProduct::class, Relation::getMorphedModel('salami_product'));
        $this->assertSame(FlowerProduct::class, Relation::getMorphedModel('flower_product'));
        $this->assertTrue($salamiProduct->stockMovements->contains($salamiMovement));
        $this->assertTrue($flowerProduct->stockMovements->contains($flowerMovement));
        $this->assertInstanceOf(SalamiProduct::class, $salamiMovement->stockable);
        $this->assertInstanceOf(FlowerProduct::class, $flowerMovement->stockable);
    }

    public function test_document_relationships_are_bound_to_their_own_module_products(): void
    {
        $supplier = Supplier::factory()->create();
        $user = User::factory()->create();
        $salamiProduct = SalamiProduct::factory()->create();
        $flowerProduct = FlowerProduct::factory()->create();
        $customer = SalamiCustomer::factory()->create();

        $salamiReceipt = SalamiReceipt::factory()->create([
            'supplier_id' => $supplier->getKey(),
            'created_by' => $user->getKey(),
        ]);
        $salamiReceiptItem = SalamiReceiptItem::factory()->create([
            'receipt_id' => $salamiReceipt->getKey(),
            'product_id' => $salamiProduct->getKey(),
        ]);
        $flowerReceipt = FlowerReceipt::factory()->create([
            'supplier_id' => $supplier->getKey(),
            'created_by' => $user->getKey(),
        ]);
        $flowerReceiptItem = FlowerReceiptItem::factory()->create([
            'receipt_id' => $flowerReceipt->getKey(),
            'flower_product_id' => $flowerProduct->getKey(),
        ]);
        $salamiInvoice = SalamiInvoice::factory()->create([
            'customer_id' => $customer->getKey(),
            'created_by' => $user->getKey(),
        ]);
        $salamiInvoiceItem = SalamiInvoiceItem::factory()->create([
            'invoice_id' => $salamiInvoice->getKey(),
            'product_id' => $salamiProduct->getKey(),
        ]);
        $flowerInvoice = FlowerInvoice::factory()->create(['created_by' => $user->getKey()]);
        $flowerInvoiceItem = FlowerInvoiceItem::factory()->create([
            'invoice_id' => $flowerInvoice->getKey(),
            'flower_product_id' => $flowerProduct->getKey(),
        ]);

        $this->assertTrue($supplier->salamiReceipts->contains($salamiReceipt));
        $this->assertTrue($supplier->flowerReceipts->contains($flowerReceipt));
        $this->assertTrue($salamiProduct->receiptItems->contains($salamiReceiptItem));
        $this->assertTrue($salamiProduct->invoiceItems->contains($salamiInvoiceItem));
        $this->assertTrue($flowerProduct->receiptItems->contains($flowerReceiptItem));
        $this->assertTrue($flowerProduct->invoiceItems->contains($flowerInvoiceItem));
        $this->assertTrue($customer->invoices->contains($salamiInvoice));
        $this->assertFalse(method_exists(FlowerInvoice::class, 'customer'));
        $this->assertFalse(Schema::hasTable('flower_customers'));
        $this->assertFalse(Schema::hasColumn('flower_invoices', 'customer_id'));
        $this->assertTrue(Schema::hasColumn('flower_invoices', 'recipient_name'));
    }

    public function test_product_numeric_attributes_are_decimal_strings_and_current_quantity_is_not_mass_assignable(): void
    {
        $product = SalamiProduct::factory()->create([
            'current_quantity' => '5.250',
            'purchase_price' => '80.500',
            'sale_price' => '100.750',
            'minimum_quantity' => '1.500',
        ]);

        $product->update(['current_quantity' => '999.000']);
        $product->refresh();

        $this->assertSame('5.250', $product->current_quantity);
        $this->assertSame('80.500', $product->purchase_price);
        $this->assertSame('100.750', $product->sale_price);
        $this->assertSame('1.500', $product->minimum_quantity);
    }

    public function test_shared_inventory_records_support_both_stockable_models(): void
    {
        $flowerProduct = FlowerProduct::factory()->create();
        $user = User::factory()->create();

        $waste = StockWaste::factory()->create([
            'stockable_type' => $flowerProduct->getMorphClass(),
            'stockable_id' => $flowerProduct->getKey(),
            'created_by' => $user->getKey(),
        ]);
        $adjustment = StockAdjustment::factory()->forFlowerProduct()->create([
            'stockable_id' => $flowerProduct->getKey(),
            'created_by' => $user->getKey(),
        ]);
        $opening = StockOpening::factory()->forFlowerProduct()->create([
            'stockable_id' => $flowerProduct->getKey(),
            'created_by' => $user->getKey(),
        ]);

        $this->assertTrue($flowerProduct->stockWastes->contains($waste));
        $this->assertTrue($flowerProduct->stockAdjustments->contains($adjustment));
        $this->assertTrue($flowerProduct->stockOpenings->contains($opening));
        $this->assertInstanceOf(FlowerProduct::class, $waste->stockable);
        $this->assertInstanceOf(FlowerProduct::class, $adjustment->stockable);
        $this->assertInstanceOf(FlowerProduct::class, $opening->stockable);
    }
}

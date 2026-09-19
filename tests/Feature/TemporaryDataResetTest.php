<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FlowerExit;
use App\Models\FlowerInvoiceItem;
use App\Models\FlowerProduct;
use App\Models\FlowerReceiptItem;
use App\Models\InvoicePayment;
use App\Models\SalamiCustomer;
use App\Models\SalamiDeliveryAgent;
use App\Models\SalamiInvoiceItem;
use App\Models\SalamiProduct;
use App\Models\SalamiReceiptItem;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockOpening;
use App\Models\StockWaste;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TemporaryDataResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_admin_can_open_or_execute_the_temporary_data_reset_tool(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $employee = User::factory()->create(['role' => UserRole::Employee]);

        $this->actingAs($employee)
            ->get('/system/temporary-data-reset')
            ->assertForbidden();

        $this->actingAs($employee)
            ->delete('/system/temporary-data-reset', ['confirmation' => 'مسح البيانات'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/system/temporary-data-reset')
            ->assertOk()
            ->assertSee('إزالة البيانات المؤقتة');
    }

    public function test_reset_removes_all_business_records_but_keeps_user_accounts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $preservedUser = User::factory()->create(['role' => UserRole::Employee]);

        Supplier::factory()->create();
        SalamiDeliveryAgent::factory()->create();
        SalamiCustomer::factory()->create();
        SalamiProduct::factory()->create();
        SalamiReceiptItem::factory()->create();
        SalamiInvoiceItem::factory()->create();
        FlowerProduct::factory()->create();
        FlowerReceiptItem::factory()->create();
        FlowerInvoiceItem::factory()->create();
        FlowerExit::factory()->create();
        InvoicePayment::factory()->create();
        StockMovement::factory()->create();
        StockWaste::factory()->create();
        StockAdjustment::factory()->create();
        StockOpening::factory()->create();

        $this->actingAs($admin)
            ->delete('/system/temporary-data-reset', ['confirmation' => 'مسح البيانات'])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status');

        foreach ($this->businessTables() as $table) {
            $this->assertSame(0, DB::table($table)->count(), "The {$table} table should be empty.");
        }

        $this->assertDatabaseHas('users', ['id' => $admin->getKey()]);
        $this->assertDatabaseHas('users', ['id' => $preservedUser->getKey()]);
    }

    public function test_reset_requires_the_exact_confirmation_phrase_before_deleting_any_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $supplier = Supplier::factory()->create();
        $product = SalamiProduct::factory()->create();

        $this->actingAs($admin)
            ->from('/system/temporary-data-reset')
            ->delete('/system/temporary-data-reset', ['confirmation' => 'احذف كل شيء'])
            ->assertRedirect('/system/temporary-data-reset')
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->getKey()]);
        $this->assertDatabaseHas('salami_products', ['id' => $product->getKey()]);
    }

    public function test_admin_can_reset_only_flower_business_data_without_deleting_suppliers_or_salami_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $supplier = Supplier::factory()->create();
        $salamiProduct = SalamiProduct::factory()->create();
        $flowerProduct = FlowerProduct::factory()->create();

        SalamiReceiptItem::factory()->create(['product_id' => $salamiProduct]);
        SalamiInvoiceItem::factory()->create(['product_id' => $salamiProduct]);
        FlowerReceiptItem::factory()->create(['flower_product_id' => $flowerProduct]);
        FlowerInvoiceItem::factory()->create(['flower_product_id' => $flowerProduct]);
        FlowerExit::factory()->create(['flower_product_id' => $flowerProduct]);
        InvoicePayment::factory()->create();
        StockMovement::factory()->forFlowerProduct()->create(['stockable_id' => $flowerProduct]);
        StockMovement::factory()->create(['stockable_id' => $salamiProduct]);
        StockWaste::factory()->forFlowerProduct()->create(['stockable_id' => $flowerProduct]);
        StockWaste::factory()->create(['stockable_id' => $salamiProduct]);
        StockAdjustment::factory()->forFlowerProduct()->create(['stockable_id' => $flowerProduct]);
        StockAdjustment::factory()->create(['stockable_id' => $salamiProduct]);
        StockOpening::factory()->forFlowerProduct()->create(['stockable_id' => $flowerProduct]);
        StockOpening::factory()->create(['stockable_id' => $salamiProduct]);

        $this->actingAs($admin)
            ->delete('/system/flower-data-reset', ['confirmation' => 'مسح بيانات الورد'])
            ->assertRedirect('/flowers/dashboard')
            ->assertSessionHas('status');

        foreach (['flower_invoice_items', 'flower_invoices', 'flower_receipt_items', 'flower_receipts', 'flower_exits', 'flower_products'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "The {$table} table should be empty.");
        }

        $this->assertSame(0, DB::table('invoice_payments')->where('invoiceable_type', 'flower_invoice')->count());
        $this->assertSame(0, DB::table('stock_movements')->where('stockable_type', 'flower_product')->count());
        $this->assertSame(0, DB::table('stock_wastes')->where('stockable_type', 'flower_product')->count());
        $this->assertSame(0, DB::table('stock_adjustments')->where('stockable_type', 'flower_product')->count());
        $this->assertSame(0, DB::table('stock_openings')->where('stockable_type', 'flower_product')->count());

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->getKey()]);
        $this->assertDatabaseHas('salami_products', ['id' => $salamiProduct->getKey()]);
        $this->assertSame(1, DB::table('stock_movements')->where('stockable_type', 'salami_product')->count());
        $this->assertSame(1, DB::table('stock_wastes')->where('stockable_type', 'salami_product')->count());
        $this->assertSame(1, DB::table('stock_adjustments')->where('stockable_type', 'salami_product')->count());
        $this->assertSame(1, DB::table('stock_openings')->where('stockable_type', 'salami_product')->count());
    }

    public function test_flower_reset_requires_its_exact_confirmation_phrase(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = FlowerProduct::factory()->create();

        $this->actingAs($admin)
            ->from('/system/flower-data-reset')
            ->delete('/system/flower-data-reset', ['confirmation' => 'مسح البيانات'])
            ->assertRedirect('/system/flower-data-reset')
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('flower_products', ['id' => $product->getKey()]);
    }

    /** @return list<string> */
    private function businessTables(): array
    {
        return [
            'invoice_payments',
            'stock_movements',
            'stock_wastes',
            'stock_adjustments',
            'stock_openings',
            'salami_invoice_items',
            'salami_invoices',
            'salami_receipt_items',
            'salami_receipts',
            'flower_invoice_items',
            'flower_invoices',
            'flower_receipt_items',
            'flower_receipts',
            'flower_exits',
            'salami_customers',
            'salami_delivery_agents',
            'salami_products',
            'flower_products',
            'suppliers',
        ];
    }
}

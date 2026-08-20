<?php

namespace Tests\Feature;

use App\Enums\SupplierScope;
use App\Enums\UserRole;
use App\Livewire\Flowers\Inventory\MovementHistory;
use App\Livewire\Flowers\Products\Form as ProductForm;
use App\Livewire\Flowers\Receipts\Form as ReceiptForm;
use App\Models\FlowerInvoice;
use App\Models\FlowerProduct;
use App\Models\FlowerReceipt;
use App\Models\SalamiProduct;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FlowerOperationsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_flower_product_without_editing_current_quantity(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(ProductForm::class)
            ->set('name', 'جوري أحمر')
            ->set('code', 'ROSE-RED')
            ->set('color', 'أحمر')
            ->set('grade', 'درجة أولى')
            ->set('unit', 'ربطة')
            ->set('purchasePrice', '12.500')
            ->set('salePrice', '18.000')
            ->set('minimumQuantity', '5.000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('flower_products', ['code' => 'ROSE-RED', 'current_quantity' => '0.000']);
    }

    public function test_employee_can_confirm_a_flower_receipt_but_cannot_reach_admin_only_flower_pages(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $supplier = Supplier::factory()->create(['module_scope' => SupplierScope::Flower]);
        $product = FlowerProduct::factory()->create(['purchase_price' => '12.500']);

        Livewire::actingAs($employee)
            ->test(ReceiptForm::class)
            ->set('supplierId', (string) $supplier->getKey())
            ->set('receiptDate', '2026-08-20')
            ->set('items.0.flower_product_id', (string) $product->getKey())
            ->set('items.0.expected_quantity', '10.000')
            ->set('items.0.received_quantity', '10.000')
            ->set('items.0.damaged_quantity', '1.000')
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertSame('9.000', $product->fresh()->current_quantity);
        $this->assertDatabaseHas('flower_receipts', ['supplier_id' => $supplier->getKey(), 'status' => 'confirmed']);

        $this->actingAs($employee)->get(route('flowers.products.create'))->assertForbidden();
        $this->actingAs($employee)->get(route('flowers.waste.create'))->assertForbidden();
        $this->actingAs($employee)->get(route('flowers.products.opening', $product))->assertForbidden();
        $this->actingAs($employee)->get(route('flowers.exits.create'))->assertOk()->assertSee('تسجيل خروج ورد');
    }

    public function test_flower_movement_history_isolated_from_salami_even_when_primary_keys_match(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $flower = FlowerProduct::factory()->create(['name' => 'ورد معزول']);
        $salami = SalamiProduct::factory()->create(['name' => 'سلامي معزول']);
        $stock = app(StockService::class);
        $stock->opening($flower, '3.000', $employee, 'flower-only-history');
        $stock->opening($salami, '7.000', $employee, 'salami-only-history');

        $this->assertSame($flower->getKey(), $salami->getKey());

        Livewire::actingAs($employee)
            ->test(MovementHistory::class, ['product' => $flower])
            ->assertSee('flower-only-history')
            ->assertDontSee('salami-only-history');
    }

    public function test_flower_navigation_has_no_customer_or_store_feature(): void
    {
        $user = User::factory()->create(['role' => UserRole::Employee]);

        $this->actingAs($user)
            ->get(route('flowers.dashboard'))
            ->assertOk()
            ->assertSee('خروج الورد')
            ->assertDontSee('المحلات')
            ->assertDontSee('العملاء');

        $this->assertFalse(collect(app('router')->getRoutes()->getRoutes())->contains(fn ($route) => str_contains($route->getName() ?? '', 'flowers.customers')));
    }

    public function test_admin_can_render_all_phase_six_flower_pages(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = FlowerProduct::factory()->create(['created_by' => $admin->getKey()]);
        $supplier = Supplier::factory()->create(['module_scope' => SupplierScope::Flower, 'created_by' => $admin->getKey()]);
        $receipt = FlowerReceipt::factory()->create(['supplier_id' => $supplier->getKey(), 'created_by' => $admin->getKey()]);

        $routes = [
            route('flowers.products.index'), route('flowers.products.create'), route('flowers.products.show', $product), route('flowers.products.edit', $product), route('flowers.products.opening', $product),
            route('flowers.suppliers.index'), route('flowers.suppliers.create'), route('flowers.suppliers.edit', $supplier),
            route('flowers.receipts.index'), route('flowers.receipts.create'), route('flowers.receipts.show', $receipt), route('flowers.receipts.edit', $receipt),
            route('flowers.inventory.index'), route('flowers.inventory.movements', $product), route('flowers.inventory.receipt-age', $product),
            route('flowers.waste.index'), route('flowers.waste.create'), route('flowers.exits.index'), route('flowers.exits.create'),
        ];

        foreach ($routes as $route) {
            $this->actingAs($admin)->get($route)->assertOk();
        }
    }

    public function test_employee_can_use_flower_invoice_and_report_pages_but_only_admin_can_cancel(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $invoice = FlowerInvoice::factory()->create(['created_by' => $employee->getKey()]);

        $this->actingAs($employee)->get(route('flowers.invoices.index'))->assertOk()->assertSee('سجل الفواتير');
        $this->actingAs($employee)->get(route('flowers.invoices.create'))->assertOk()->assertSee('فاتورة ورد جديدة');
        $this->actingAs($employee)->get(route('flowers.invoices.show', $invoice))->assertOk();
        $this->actingAs($employee)->get(route('flowers.invoices.print', $invoice))->assertOk()->assertSee('فاتورة بيع');
        $this->actingAs($employee)->get(route('flowers.reports.index'))->assertOk()->assertSee('تقارير الورد');
        $this->assertFalse($employee->can('cancel-flower-invoices'));
        $this->assertTrue($admin->can('cancel-flower-invoices'));
    }
}

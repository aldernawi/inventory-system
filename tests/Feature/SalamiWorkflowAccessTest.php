<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FlowerProduct;
use App\Models\SalamiCustomer;
use App\Models\SalamiProduct;
use App\Models\SalamiReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalamiWorkflowAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_view_salami_data_and_operate_receipts_but_cannot_manage_master_data_or_opening_stock(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $product = SalamiProduct::factory()->create();
        $supplier = Supplier::factory()->create();
        $customer = SalamiCustomer::factory()->create();
        $receipt = SalamiReceipt::factory()->create(['supplier_id' => $supplier->getKey(), 'created_by' => $employee->getKey()]);

        $this->actingAs($employee)
            ->get(route('salami.products.index'))
            ->assertOk()
            ->assertSee('أصناف السلامي');

        $this->actingAs($employee)
            ->get(route('salami.suppliers.index'))
            ->assertOk()
            ->assertSee($supplier->name);

        $this->actingAs($employee)
            ->get(route('salami.customers.show', $customer))
            ->assertOk()
            ->assertSee($customer->name);

        $this->actingAs($employee)
            ->get(route('salami.receipts.create'))
            ->assertOk()
            ->assertSee('استلام بضاعة جديدة');

        $this->actingAs($employee)
            ->get(route('salami.receipts.edit', $receipt))
            ->assertOk();

        $this->actingAs($employee)
            ->get(route('salami.inventory.index'))
            ->assertOk()
            ->assertSee('المخزون الحالي للسلامي');

        $this->actingAs($employee)
            ->get(route('salami.products.create'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('salami.products.edit', $product))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('salami.products.opening', $product))
            ->assertForbidden();
    }

    public function test_admin_can_reach_salami_master_data_and_opening_stock_pages(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = SalamiProduct::factory()->create();

        $this->actingAs($admin)
            ->get(route('salami.products.create'))
            ->assertOk()
            ->assertSee('إضافة صنف جديد');

        $this->actingAs($admin)
            ->get(route('salami.suppliers.create'))
            ->assertOk()
            ->assertSee('إضافة مورد');

        $this->actingAs($admin)
            ->get(route('salami.customers.create'))
            ->assertOk()
            ->assertSee('إضافة محل');

        $this->actingAs($admin)
            ->get(route('salami.products.opening', $product))
            ->assertOk()
            ->assertSee('تسجيل رصيد افتتاحي');
    }

    public function test_salami_movement_history_never_includes_flower_movements_with_the_same_numeric_id(): void
    {
        $user = User::factory()->create();
        $salamiProduct = SalamiProduct::factory()->create();
        $flowerProduct = FlowerProduct::factory()->create();
        $stock = app(StockService::class);

        $this->assertSame($salamiProduct->getKey(), $flowerProduct->getKey());

        $stock->opening($salamiProduct, '20.000', $user, 'salami-history-only');
        $stock->opening($flowerProduct, '100.000', $user, 'flower-history-only');

        $this->actingAs($user)
            ->get(route('salami.inventory.movements', $salamiProduct))
            ->assertOk()
            ->assertSee('salami-history-only')
            ->assertDontSee('flower-history-only');
    }
}

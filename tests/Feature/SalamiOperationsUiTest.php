<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Salami\Invoices\Form as InvoiceForm;
use App\Livewire\Salami\Reports\Index as ReportsIndex;
use App\Models\FlowerProduct;
use App\Models\SalamiCustomer;
use App\Models\SalamiInvoice;
use App\Models\SalamiProduct;
use App\Models\User;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalamiOperationsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_create_and_confirm_an_invoice_through_the_livewire_workflow(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $customer = SalamiCustomer::factory()->create();
        $product = SalamiProduct::factory()->create(['sale_price' => '100.000']);
        app(StockService::class)->opening($product, '10.000', $employee);

        Livewire::actingAs($employee)
            ->test(InvoiceForm::class)
            ->set('customerId', (string) $customer->getKey())
            ->set('invoiceDate', '2026-08-20')
            ->set('paymentType', 'cash')
            ->set('paidAmount', '200.000')
            ->set('items.0.product_id', (string) $product->getKey())
            ->set('items.0.quantity', '2.000')
            ->set('items.0.unit_price', '100.000')
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertSame('8.000', $product->fresh()->current_quantity);
        $this->assertDatabaseHas('salami_invoices', ['customer_id' => $customer->getKey(), 'status' => 'confirmed']);
    }

    public function test_reports_only_include_salami_stock_records(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $salamiProduct = SalamiProduct::factory()->create(['name' => 'صنف تقرير السلامي']);
        $flowerProduct = FlowerProduct::factory()->create(['name' => 'صنف تقرير الورد']);
        $stock = app(StockService::class);
        $stock->opening($salamiProduct, '10.000', $employee, 'salami-report-only');
        $stock->opening($flowerProduct, '10.000', $employee, 'flower-report-only');

        Livewire::actingAs($employee)
            ->test(ReportsIndex::class)
            ->set('report', 'movements')
            ->assertSee('صنف تقرير السلامي')
            ->assertDontSee('صنف تقرير الورد');
    }

    public function test_printing_requires_an_authenticated_active_user_and_employees_cannot_reach_admin_stock_pages(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $invoice = SalamiInvoice::factory()->create(['created_by' => $employee->getKey()]);

        $this->get(route('salami.invoices.print', $invoice))
            ->assertRedirect(route('login'));

        $this->actingAs($employee)
            ->get(route('salami.invoices.print', $invoice))
            ->assertOk()
            ->assertSee($invoice->invoice_number);

        $this->actingAs($employee)
            ->get(route('salami.waste.create'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('salami.adjustments.create'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('salami.reports.index'))
            ->assertOk()
            ->assertSee('تقارير السلامي');

        $this->actingAs($admin)
            ->get(route('salami.waste.create'))
            ->assertOk()
            ->assertSee('تسجيل تالف سلامي');

        $this->actingAs($admin)
            ->get(route('salami.adjustments.create'))
            ->assertOk()
            ->assertSee('جرد فعلي وتسوية سلامي');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ReceiptStatus;
use App\Enums\UserRole;
use App\Livewire\Salami\Products\Form as ProductForm;
use App\Livewire\Salami\Receipts\Form as ReceiptForm;
use App\Models\SalamiProduct;
use App\Models\SalamiReceipt;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalamiLivewireFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_product_form_creates_a_product_with_zero_stock_and_no_stock_movement(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(ProductForm::class)
            ->set('name', 'سلامي اختبار')
            ->set('code', 'SAL-UI-001')
            ->set('unit', 'كرتونة')
            ->set('purchasePrice', '80.125')
            ->set('salePrice', '100.500')
            ->set('minimumQuantity', '10.000')
            ->call('save')
            ->assertHasNoErrors();

        $product = SalamiProduct::query()->where('code', 'SAL-UI-001')->sole();

        $this->assertSame('0.000', $product->current_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_employee_receipt_form_creates_and_confirms_a_receipt_through_the_service_layer(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $supplier = Supplier::factory()->create();
        $product = SalamiProduct::factory()->create(['purchase_price' => '80.125']);

        Livewire::actingAs($employee)
            ->test(ReceiptForm::class)
            ->set('supplierId', (string) $supplier->getKey())
            ->set('receiptDate', '2026-08-20')
            ->set('items.0.product_id', (string) $product->getKey())
            ->set('items.0.expected_quantity', '100.000')
            ->set('items.0.received_quantity', '94.000')
            ->set('items.0.damaged_quantity', '4.000')
            ->set('items.0.purchase_price', '80.125')
            ->call('confirm')
            ->assertHasNoErrors();

        $receipt = SalamiReceipt::query()->sole();

        $this->assertSame(ReceiptStatus::Confirmed, $receipt->status);
        $this->assertSame('90.000', $product->fresh()->current_quantity);
        $this->assertDatabaseCount('stock_movements', 1);
    }
}

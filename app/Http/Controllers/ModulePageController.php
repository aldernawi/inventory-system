<?php

namespace App\Http\Controllers;

use App\Models\FlowerInvoice;
use App\Models\FlowerProduct;
use App\Models\FlowerReceipt;
use App\Models\SalamiCustomer;
use App\Models\SalamiInvoice;
use App\Models\SalamiProduct;
use App\Models\SalamiReceipt;
use App\Models\Supplier;
use Illuminate\View\View;

class ModulePageController extends Controller
{
    public function salamiProduct(SalamiProduct $product, string $page): View
    {
        return $this->salami(match ($page) {
            'edit' => ['إضافة صنف | إدارة مخزن السلامي', 'salami.products.form'],
            'opening' => ['رصيد افتتاحي | إدارة مخزن السلامي', 'salami.products.opening-stock'],
            'show' => ['تفاصيل صنف | إدارة مخزن السلامي', 'salami.products.show'],
        }, compact('product'));
    }

    public function salamiSupplier(Supplier $supplier): View
    {
        return $this->salami(['تعديل مورد | إدارة مخزن السلامي', 'salami.suppliers.form'], compact('supplier'));
    }

    public function salamiCustomer(SalamiCustomer $customer, string $page): View
    {
        return $this->salami(match ($page) {
            'edit' => ['تعديل محل | إدارة مخزن السلامي', 'salami.customers.form'],
            'show' => ['تفاصيل محل | إدارة مخزن السلامي', 'salami.customers.show'],
        }, compact('customer'));
    }

    public function salamiReceipt(SalamiReceipt $receipt, string $page): View
    {
        return $this->salami(match ($page) {
            'edit' => ['تعديل استلام | إدارة مخزن السلامي', 'salami.receipts.form'],
            'show' => ['تفاصيل استلام | إدارة مخزن السلامي', 'salami.receipts.show'],
        }, compact('receipt'));
    }

    public function salamiInvoice(SalamiInvoice $invoice, string $page): View
    {
        return $this->salami(match ($page) {
            'edit' => ['تعديل فاتورة | إدارة مخزن السلامي', 'salami.invoices.form'],
            'show' => ['تفاصيل فاتورة | إدارة مخزن السلامي', 'salami.invoices.show'],
        }, compact('invoice'));
    }

    public function salamiMovement(SalamiProduct $product): View
    {
        return $this->salami(['حركة الصنف | إدارة مخزن السلامي', 'salami.inventory.movement-history'], compact('product'));
    }

    public function flowerProduct(FlowerProduct $product, string $page): View
    {
        return $this->flowers(match ($page) {
            'edit' => ['تعديل نوع ورد | إدارة مخزون الورد', 'flowers.products.form'],
            'opening' => ['رصيد افتتاحي | إدارة مخزون الورد', 'flowers.products.opening-stock'],
            'show' => ['تفاصيل نوع ورد | إدارة مخزون الورد', 'flowers.products.show'],
            'movements' => ['حركة نوع الورد | إدارة مخزون الورد', 'flowers.inventory.movement-history'],
            'receipt-age' => ['سجل وصول نوع الورد | إدارة مخزون الورد', 'flowers.inventory.receipt-age'],
        }, compact('product'));
    }

    public function flowerSupplier(Supplier $supplier): View
    {
        return $this->flowers(['تعديل مورد ورد | إدارة مخزون الورد', 'flowers.suppliers.form'], compact('supplier'));
    }

    public function flowerReceipt(FlowerReceipt $receipt, string $page): View
    {
        return $this->flowers(match ($page) {
            'edit' => ['تعديل استلام ورد | إدارة مخزون الورد', 'flowers.receipts.form'],
            'show' => ['تفاصيل استلام ورد | إدارة مخزون الورد', 'flowers.receipts.show'],
        }, compact('receipt'));
    }

    public function flowerInvoice(FlowerInvoice $invoice, string $page): View
    {
        return $this->flowers(match ($page) {
            'edit' => ['تعديل فاتورة ورد | إدارة مخزون الورد', 'flowers.invoices.form'],
            'show' => ['تفاصيل فاتورة ورد | إدارة مخزون الورد', 'flowers.invoices.show'],
        }, compact('invoice'));
    }

    /** @param array{string, string} $page */
    private function salami(array $page, array $parameters = []): View
    {
        return view('salami.livewire-page', ['title' => $page[0], 'component' => $page[1], 'parameters' => $parameters]);
    }

    /** @param array{string, string} $page */
    private function flowers(array $page, array $parameters = []): View
    {
        return view('flowers.livewire-page', ['title' => $page[0], 'component' => $page[1], 'parameters' => $parameters]);
    }
}

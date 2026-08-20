<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Models\FlowerProduct;
use App\Models\FlowerReceipt;
use App\Models\SalamiCustomer;
use App\Models\SalamiInvoice;
use App\Models\SalamiProduct;
use App\Models\SalamiReceipt;
use App\Models\Supplier;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    $salamiPage = static fn (string $title, string $component, array $parameters = []) => view('salami.livewire-page', compact('title', 'component', 'parameters'));

    Route::prefix('salami')->as('salami.')->group(function () use ($salamiPage): void {
        Route::get('/dashboard', fn () => $salamiPage('الرئيسية | إدارة مخزن السلامي', 'salami.dashboard.index'))->name('dashboard');

        Route::get('/products', fn () => $salamiPage('الأصناف | إدارة مخزن السلامي', 'salami.products.index'))->name('products.index');
        Route::get('/products/create', fn () => $salamiPage('إضافة صنف | إدارة مخزن السلامي', 'salami.products.form'))->middleware('can:manage-salami-master-data')->name('products.create');
        Route::get('/products/{product}/edit', fn (SalamiProduct $product) => $salamiPage('تعديل صنف | إدارة مخزن السلامي', 'salami.products.form', compact('product')))->middleware('can:manage-salami-master-data')->name('products.edit');
        Route::get('/products/{product}/opening-stock', fn (SalamiProduct $product) => $salamiPage('رصيد افتتاحي | إدارة مخزن السلامي', 'salami.products.opening-stock', compact('product')))->middleware('can:register-salami-opening-stock')->name('products.opening');
        Route::get('/products/{product}', fn (SalamiProduct $product) => $salamiPage('تفاصيل صنف | إدارة مخزن السلامي', 'salami.products.show', compact('product')))->name('products.show');

        Route::get('/suppliers', fn () => $salamiPage('الموردون | إدارة مخزن السلامي', 'salami.suppliers.index'))->name('suppliers.index');
        Route::get('/suppliers/create', fn () => $salamiPage('إضافة مورد | إدارة مخزن السلامي', 'salami.suppliers.form'))->middleware('can:manage-salami-master-data')->name('suppliers.create');
        Route::get('/suppliers/{supplier}/edit', fn (Supplier $supplier) => $salamiPage('تعديل مورد | إدارة مخزن السلامي', 'salami.suppliers.form', compact('supplier')))->middleware('can:manage-salami-master-data')->name('suppliers.edit');

        Route::get('/customers', fn () => $salamiPage('المحلات | إدارة مخزن السلامي', 'salami.customers.index'))->name('customers.index');
        Route::get('/customers/create', fn () => $salamiPage('إضافة محل | إدارة مخزن السلامي', 'salami.customers.form'))->middleware('can:manage-salami-master-data')->name('customers.create');
        Route::get('/customers/{customer}/edit', fn (SalamiCustomer $customer) => $salamiPage('تعديل محل | إدارة مخزن السلامي', 'salami.customers.form', compact('customer')))->middleware('can:manage-salami-master-data')->name('customers.edit');
        Route::get('/customers/{customer}', fn (SalamiCustomer $customer) => $salamiPage('تفاصيل محل | إدارة مخزن السلامي', 'salami.customers.show', compact('customer')))->name('customers.show');

        Route::get('/receipts', fn () => $salamiPage('سجل الاستلامات | إدارة مخزن السلامي', 'salami.receipts.index'))->name('receipts.index');
        Route::get('/receipts/create', fn () => $salamiPage('إضافة مخزون | إدارة مخزن السلامي', 'salami.receipts.form'))->middleware('can:operate-salami-receipts')->name('receipts.create');
        Route::get('/receipts/{receipt}/edit', fn (SalamiReceipt $receipt) => $salamiPage('تعديل استلام | إدارة مخزن السلامي', 'salami.receipts.form', compact('receipt')))->middleware('can:operate-salami-receipts')->name('receipts.edit');
        Route::get('/receipts/{receipt}', fn (SalamiReceipt $receipt) => $salamiPage('تفاصيل استلام | إدارة مخزن السلامي', 'salami.receipts.show', compact('receipt')))->name('receipts.show');

        Route::get('/inventory', fn () => $salamiPage('المخزون الحالي | إدارة مخزن السلامي', 'salami.inventory.index'))->name('inventory.index');
        Route::get('/inventory/{product}/movements', fn (SalamiProduct $product) => $salamiPage('حركة الصنف | إدارة مخزن السلامي', 'salami.inventory.movement-history', compact('product')))->name('inventory.movements');

        Route::get('/invoices', fn () => $salamiPage('سجل الفواتير | إدارة مخزن السلامي', 'salami.invoices.index'))->name('invoices.index');
        Route::get('/invoices/create', fn () => $salamiPage('فاتورة جديدة | إدارة مخزن السلامي', 'salami.invoices.form'))->middleware('can:operate-salami-invoices')->name('invoices.create');
        Route::get('/invoices/{invoice}/edit', fn (SalamiInvoice $invoice) => $salamiPage('تعديل فاتورة | إدارة مخزن السلامي', 'salami.invoices.form', compact('invoice')))->middleware('can:operate-salami-invoices')->name('invoices.edit');
        Route::get('/invoices/{invoice}', fn (SalamiInvoice $invoice) => $salamiPage('تفاصيل فاتورة | إدارة مخزن السلامي', 'salami.invoices.show', compact('invoice')))->name('invoices.show');
        Route::get('/invoices/{invoice}/print', function (SalamiInvoice $invoice) {
            return view('salami.invoices.print', [
                'invoice' => $invoice->load(['customer', 'items.product', 'createdBy', 'confirmedBy', 'cancelledBy']),
            ]);
        })->middleware('can:operate-salami-invoices')->name('invoices.print');

        Route::get('/waste', fn () => $salamiPage('سجل التالف | إدارة مخزن السلامي', 'salami.waste.index'))->name('waste.index');
        Route::get('/waste/create', fn () => $salamiPage('تسجيل تالف | إدارة مخزن السلامي', 'salami.waste.form'))->middleware('can:register-salami-waste')->name('waste.create');
        Route::get('/adjustments', fn () => $salamiPage('تسوية المخزون | إدارة مخزن السلامي', 'salami.adjustments.index'))->middleware('can:perform-salami-adjustments')->name('adjustments.index');
        Route::get('/adjustments/create', fn () => $salamiPage('تسوية المخزون | إدارة مخزن السلامي', 'salami.adjustments.form'))->middleware('can:perform-salami-adjustments')->name('adjustments.create');
        Route::get('/reports', fn () => $salamiPage('تقارير السلامي | إدارة مخزن السلامي', 'salami.reports.index'))->middleware('can:view-salami-reports')->name('reports.index');
    });

    $flowersPage = static fn (string $title, string $component, array $parameters = []) => view('flowers.livewire-page', compact('title', 'component', 'parameters'));

    Route::prefix('flowers')->as('flowers.')->group(function () use ($flowersPage): void {
        Route::get('/dashboard', fn () => $flowersPage('الرئيسية | إدارة مخزون الورد', 'flowers.dashboard.index'))->name('dashboard');

        Route::get('/products', fn () => $flowersPage('أنواع الورد | إدارة مخزون الورد', 'flowers.products.index'))->name('products.index');
        Route::get('/products/create', fn () => $flowersPage('إضافة نوع ورد | إدارة مخزون الورد', 'flowers.products.form'))->middleware('can:manage-flower-master-data')->name('products.create');
        Route::get('/products/{product}/edit', fn (FlowerProduct $product) => $flowersPage('تعديل نوع ورد | إدارة مخزون الورد', 'flowers.products.form', compact('product')))->middleware('can:manage-flower-master-data')->name('products.edit');
        Route::get('/products/{product}/opening-stock', fn (FlowerProduct $product) => $flowersPage('رصيد افتتاحي | إدارة مخزون الورد', 'flowers.products.opening-stock', compact('product')))->middleware('can:register-flower-opening-stock')->name('products.opening');
        Route::get('/products/{product}', fn (FlowerProduct $product) => $flowersPage('تفاصيل نوع ورد | إدارة مخزون الورد', 'flowers.products.show', compact('product')))->name('products.show');

        Route::get('/suppliers', fn () => $flowersPage('موردو الورد | إدارة مخزون الورد', 'flowers.suppliers.index'))->name('suppliers.index');
        Route::get('/suppliers/create', fn () => $flowersPage('إضافة مورد ورد | إدارة مخزون الورد', 'flowers.suppliers.form'))->middleware('can:manage-flower-master-data')->name('suppliers.create');
        Route::get('/suppliers/{supplier}/edit', fn (Supplier $supplier) => $flowersPage('تعديل مورد ورد | إدارة مخزون الورد', 'flowers.suppliers.form', compact('supplier')))->middleware('can:manage-flower-master-data')->name('suppliers.edit');

        Route::get('/receipts', fn () => $flowersPage('سجل استلامات الورد | إدارة مخزون الورد', 'flowers.receipts.index'))->name('receipts.index');
        Route::get('/receipts/create', fn () => $flowersPage('استلام وإضافة مخزون الورد | إدارة مخزون الورد', 'flowers.receipts.form'))->middleware('can:operate-flower-receipts')->name('receipts.create');
        Route::get('/receipts/{receipt}/edit', fn (FlowerReceipt $receipt) => $flowersPage('تعديل استلام ورد | إدارة مخزون الورد', 'flowers.receipts.form', compact('receipt')))->middleware('can:operate-flower-receipts')->name('receipts.edit');
        Route::get('/receipts/{receipt}', fn (FlowerReceipt $receipt) => $flowersPage('تفاصيل استلام ورد | إدارة مخزون الورد', 'flowers.receipts.show', compact('receipt')))->name('receipts.show');

        Route::get('/inventory', fn () => $flowersPage('المخزون الحالي | إدارة مخزون الورد', 'flowers.inventory.index'))->name('inventory.index');
        Route::get('/inventory/{product}/movements', fn (FlowerProduct $product) => $flowersPage('حركة نوع الورد | إدارة مخزون الورد', 'flowers.inventory.movement-history', compact('product')))->name('inventory.movements');
        Route::get('/inventory/{product}/receipt-age', fn (FlowerProduct $product) => $flowersPage('سجل وصول نوع الورد | إدارة مخزون الورد', 'flowers.inventory.receipt-age', compact('product')))->name('inventory.receipt-age');

        Route::get('/waste', fn () => $flowersPage('سجل تالف الورد | إدارة مخزون الورد', 'flowers.waste.index'))->name('waste.index');
        Route::get('/waste/create', fn () => $flowersPage('تسجيل تالف ورد | إدارة مخزون الورد', 'flowers.waste.form'))->middleware('can:register-flower-waste')->name('waste.create');
        Route::get('/exits', fn () => $flowersPage('خروج الورد | إدارة مخزون الورد', 'flowers.exits.index'))->name('exits.index');
        Route::get('/exits/create', fn () => $flowersPage('تسجيل خروج ورد | إدارة مخزون الورد', 'flowers.exits.form'))->middleware('can:operate-flower-exits')->name('exits.create');
    });
});

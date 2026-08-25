<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoicePrintController;
use App\Http\Controllers\ModulePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'home']);

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/dashboard', [HomeController::class, 'dashboard'])->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::prefix('salami')->as('salami.')->group(function (): void {
        Route::view('/dashboard', 'salami.livewire-page', ['title' => 'الرئيسية | إدارة مخزن السلامي', 'component' => 'salami.dashboard.index', 'parameters' => []])->name('dashboard');
        Route::view('/products', 'salami.livewire-page', ['title' => 'الأصناف | إدارة مخزن السلامي', 'component' => 'salami.products.index', 'parameters' => []])->name('products.index');
        Route::view('/products/create', 'salami.livewire-page', ['title' => 'إضافة صنف | إدارة مخزن السلامي', 'component' => 'salami.products.form', 'parameters' => []])->middleware('can:manage-salami-master-data')->name('products.create');
        Route::get('/products/{product}/edit', [ModulePageController::class, 'salamiProduct'])->defaults('page', 'edit')->middleware('can:manage-salami-master-data')->name('products.edit');
        Route::get('/products/{product}/opening-stock', [ModulePageController::class, 'salamiProduct'])->defaults('page', 'opening')->middleware('can:register-salami-opening-stock')->name('products.opening');
        Route::get('/products/{product}', [ModulePageController::class, 'salamiProduct'])->defaults('page', 'show')->name('products.show');

        Route::view('/suppliers', 'salami.livewire-page', ['title' => 'الموردون | إدارة مخزن السلامي', 'component' => 'salami.suppliers.index', 'parameters' => []])->name('suppliers.index');
        Route::view('/suppliers/create', 'salami.livewire-page', ['title' => 'إضافة مورد | إدارة مخزن السلامي', 'component' => 'salami.suppliers.form', 'parameters' => []])->middleware('can:manage-salami-master-data')->name('suppliers.create');
        Route::get('/suppliers/{supplier}/edit', [ModulePageController::class, 'salamiSupplier'])->middleware('can:manage-salami-master-data')->name('suppliers.edit');

        Route::view('/customers', 'salami.livewire-page', ['title' => 'المحلات | إدارة مخزن السلامي', 'component' => 'salami.customers.index', 'parameters' => []])->name('customers.index');
        Route::view('/customers/create', 'salami.livewire-page', ['title' => 'إضافة محل | إدارة مخزن السلامي', 'component' => 'salami.customers.form', 'parameters' => []])->middleware('can:manage-salami-master-data')->name('customers.create');
        Route::get('/customers/{customer}/edit', [ModulePageController::class, 'salamiCustomer'])->defaults('page', 'edit')->middleware('can:manage-salami-master-data')->name('customers.edit');
        Route::get('/customers/{customer}', [ModulePageController::class, 'salamiCustomer'])->defaults('page', 'show')->name('customers.show');

        Route::view('/delivery-agents', 'salami.livewire-page', ['title' => 'مندوبو التوصيل | إدارة مخزن السلامي', 'component' => 'salami.delivery-agents.index', 'parameters' => []])->middleware('can:manage-salami-master-data')->name('delivery-agents.index');
        Route::view('/delivery-agents/create', 'salami.livewire-page', ['title' => 'إضافة مندوب | إدارة مخزن السلامي', 'component' => 'salami.delivery-agents.form', 'parameters' => []])->middleware('can:manage-salami-master-data')->name('delivery-agents.create');
        Route::get('/delivery-agents/{deliveryAgent}/edit', [ModulePageController::class, 'salamiDeliveryAgent'])->middleware('can:manage-salami-master-data')->name('delivery-agents.edit');

        Route::view('/receipts', 'salami.livewire-page', ['title' => 'سجل الاستلامات | إدارة مخزن السلامي', 'component' => 'salami.receipts.index', 'parameters' => []])->name('receipts.index');
        Route::view('/receipts/create', 'salami.livewire-page', ['title' => 'إضافة مخزون | إدارة مخزن السلامي', 'component' => 'salami.receipts.form', 'parameters' => []])->middleware('can:operate-salami-receipts')->name('receipts.create');
        Route::get('/receipts/{receipt}/edit', [ModulePageController::class, 'salamiReceipt'])->defaults('page', 'edit')->middleware('can:operate-salami-receipts')->name('receipts.edit');
        Route::get('/receipts/{receipt}', [ModulePageController::class, 'salamiReceipt'])->defaults('page', 'show')->name('receipts.show');

        Route::view('/inventory', 'salami.livewire-page', ['title' => 'المخزون الحالي | إدارة مخزن السلامي', 'component' => 'salami.inventory.index', 'parameters' => []])->name('inventory.index');
        Route::get('/inventory/{product}/movements', [ModulePageController::class, 'salamiMovement'])->name('inventory.movements');

        Route::view('/invoices', 'salami.livewire-page', ['title' => 'سجل الفواتير | إدارة مخزن السلامي', 'component' => 'salami.invoices.index', 'parameters' => []])->name('invoices.index');
        Route::view('/invoices/create', 'salami.livewire-page', ['title' => 'فاتورة جديدة | إدارة مخزن السلامي', 'component' => 'salami.invoices.form', 'parameters' => []])->middleware('can:operate-salami-invoices')->name('invoices.create');
        Route::get('/invoices/{invoice}/edit', [ModulePageController::class, 'salamiInvoice'])->defaults('page', 'edit')->middleware('can:operate-salami-invoices')->name('invoices.edit');
        Route::get('/invoices/{invoice}', [ModulePageController::class, 'salamiInvoice'])->defaults('page', 'show')->name('invoices.show');
        Route::get('/invoices/{invoice}/print', [InvoicePrintController::class, 'salami'])->middleware('can:operate-salami-invoices')->name('invoices.print');

        Route::view('/waste', 'salami.livewire-page', ['title' => 'سجل التالف | إدارة مخزن السلامي', 'component' => 'salami.waste.index', 'parameters' => []])->name('waste.index');
        Route::view('/waste/create', 'salami.livewire-page', ['title' => 'تسجيل تالف | إدارة مخزن السلامي', 'component' => 'salami.waste.form', 'parameters' => []])->middleware('can:register-salami-waste')->name('waste.create');
        Route::view('/adjustments', 'salami.livewire-page', ['title' => 'تسوية المخزون | إدارة مخزن السلامي', 'component' => 'salami.adjustments.index', 'parameters' => []])->middleware('can:perform-salami-adjustments')->name('adjustments.index');
        Route::view('/adjustments/create', 'salami.livewire-page', ['title' => 'تسوية المخزون | إدارة مخزن السلامي', 'component' => 'salami.adjustments.form', 'parameters' => []])->middleware('can:perform-salami-adjustments')->name('adjustments.create');
        Route::view('/reports', 'salami.livewire-page', ['title' => 'تقارير السلامي | إدارة مخزن السلامي', 'component' => 'salami.reports.index', 'parameters' => []])->middleware('can:view-salami-reports')->name('reports.index');
    });

    Route::prefix('flowers')->as('flowers.')->group(function (): void {
        Route::view('/dashboard', 'flowers.livewire-page', ['title' => 'الرئيسية | إدارة مخزون الورد', 'component' => 'flowers.dashboard.index', 'parameters' => []])->name('dashboard');
        Route::view('/products', 'flowers.livewire-page', ['title' => 'أنواع الورد | إدارة مخزون الورد', 'component' => 'flowers.products.index', 'parameters' => []])->name('products.index');
        Route::view('/products/create', 'flowers.livewire-page', ['title' => 'إضافة نوع ورد | إدارة مخزون الورد', 'component' => 'flowers.products.form', 'parameters' => []])->middleware('can:manage-flower-master-data')->name('products.create');
        Route::get('/products/{product}/edit', [ModulePageController::class, 'flowerProduct'])->defaults('page', 'edit')->middleware('can:manage-flower-master-data')->name('products.edit');
        Route::get('/products/{product}/opening-stock', [ModulePageController::class, 'flowerProduct'])->defaults('page', 'opening')->middleware('can:register-flower-opening-stock')->name('products.opening');
        Route::get('/products/{product}', [ModulePageController::class, 'flowerProduct'])->defaults('page', 'show')->name('products.show');

        Route::view('/suppliers', 'flowers.livewire-page', ['title' => 'موردو الورد | إدارة مخزون الورد', 'component' => 'flowers.suppliers.index', 'parameters' => []])->name('suppliers.index');
        Route::view('/suppliers/create', 'flowers.livewire-page', ['title' => 'إضافة مورد ورد | إدارة مخزون الورد', 'component' => 'flowers.suppliers.form', 'parameters' => []])->middleware('can:manage-flower-master-data')->name('suppliers.create');
        Route::get('/suppliers/{supplier}/edit', [ModulePageController::class, 'flowerSupplier'])->middleware('can:manage-flower-master-data')->name('suppliers.edit');

        Route::view('/receipts', 'flowers.livewire-page', ['title' => 'سجل استلامات الورد | إدارة مخزون الورد', 'component' => 'flowers.receipts.index', 'parameters' => []])->name('receipts.index');
        Route::view('/receipts/create', 'flowers.livewire-page', ['title' => 'استلام وإضافة مخزون الورد | إدارة مخزون الورد', 'component' => 'flowers.receipts.form', 'parameters' => []])->middleware('can:operate-flower-receipts')->name('receipts.create');
        Route::get('/receipts/{receipt}/edit', [ModulePageController::class, 'flowerReceipt'])->defaults('page', 'edit')->middleware('can:operate-flower-receipts')->name('receipts.edit');
        Route::get('/receipts/{receipt}', [ModulePageController::class, 'flowerReceipt'])->defaults('page', 'show')->name('receipts.show');

        Route::view('/inventory', 'flowers.livewire-page', ['title' => 'المخزون الحالي | إدارة مخزون الورد', 'component' => 'flowers.inventory.index', 'parameters' => []])->name('inventory.index');
        Route::get('/inventory/{product}/movements', [ModulePageController::class, 'flowerProduct'])->defaults('page', 'movements')->name('inventory.movements');
        Route::get('/inventory/{product}/receipt-age', [ModulePageController::class, 'flowerProduct'])->defaults('page', 'receipt-age')->name('inventory.receipt-age');

        Route::view('/waste', 'flowers.livewire-page', ['title' => 'سجل تالف الورد | إدارة مخزون الورد', 'component' => 'flowers.waste.index', 'parameters' => []])->name('waste.index');
        Route::view('/waste/create', 'flowers.livewire-page', ['title' => 'تسجيل تالف ورد | إدارة مخزون الورد', 'component' => 'flowers.waste.form', 'parameters' => []])->middleware('can:register-flower-waste')->name('waste.create');
        Route::view('/exits', 'flowers.livewire-page', ['title' => 'خروج الورد | إدارة مخزون الورد', 'component' => 'flowers.exits.index', 'parameters' => []])->name('exits.index');
        Route::view('/exits/create', 'flowers.livewire-page', ['title' => 'تسجيل خروج ورد | إدارة مخزون الورد', 'component' => 'flowers.exits.form', 'parameters' => []])->middleware('can:operate-flower-exits')->name('exits.create');

        Route::view('/invoices', 'flowers.livewire-page', ['title' => 'سجل فواتير الورد | إدارة مخزون الورد', 'component' => 'flowers.invoices.index', 'parameters' => []])->name('invoices.index');
        Route::view('/invoices/create', 'flowers.livewire-page', ['title' => 'فاتورة ورد جديدة | إدارة مخزون الورد', 'component' => 'flowers.invoices.form', 'parameters' => []])->middleware('can:operate-flower-invoices')->name('invoices.create');
        Route::get('/invoices/{invoice}/edit', [ModulePageController::class, 'flowerInvoice'])->defaults('page', 'edit')->middleware('can:operate-flower-invoices')->name('invoices.edit');
        Route::get('/invoices/{invoice}', [ModulePageController::class, 'flowerInvoice'])->defaults('page', 'show')->name('invoices.show');
        Route::get('/invoices/{invoice}/print', [InvoicePrintController::class, 'flowers'])->middleware('can:operate-flower-invoices')->name('invoices.print');
        Route::view('/reports', 'flowers.livewire-page', ['title' => 'تقارير الورد | إدارة مخزون الورد', 'component' => 'flowers.reports.index', 'parameters' => []])->middleware('can:view-flower-reports')->name('reports.index');
    });
});

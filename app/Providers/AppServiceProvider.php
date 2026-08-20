<?php

namespace App\Providers;

use App\Models\FlowerExit;
use App\Models\FlowerInvoiceItem;
use App\Models\FlowerProduct;
use App\Models\FlowerReceiptItem;
use App\Models\SalamiInvoiceItem;
use App\Models\SalamiProduct;
use App\Models\SalamiReceiptItem;
use App\Models\StockAdjustment;
use App\Models\StockOpening;
use App\Models\StockWaste;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-salami-master-data', fn (User $user): bool => $user->isAdmin());
        Gate::define('register-salami-opening-stock', fn (User $user): bool => $user->isAdmin());
        Gate::define('operate-salami-receipts', fn (User $user): bool => $user->is_active);

        Relation::enforceMorphMap([
            'salami_product' => SalamiProduct::class,
            'flower_product' => FlowerProduct::class,
            'salami_receipt_item' => SalamiReceiptItem::class,
            'flower_receipt_item' => FlowerReceiptItem::class,
            'salami_invoice_item' => SalamiInvoiceItem::class,
            'flower_invoice_item' => FlowerInvoiceItem::class,
            'flower_exit' => FlowerExit::class,
            'stock_waste' => StockWaste::class,
            'stock_adjustment' => StockAdjustment::class,
            'stock_opening' => StockOpening::class,
        ]);
    }
}

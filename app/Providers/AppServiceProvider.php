<?php

namespace App\Providers;

use App\Models\FlowerExit;
use App\Models\FlowerInvoice;
use App\Models\FlowerInvoiceItem;
use App\Models\FlowerProduct;
use App\Models\FlowerReceiptItem;
use App\Models\SalamiInvoice;
use App\Models\SalamiInvoiceItem;
use App\Models\SalamiProduct;
use App\Models\SalamiReceiptItem;
use App\Models\StockAdjustment;
use App\Models\StockOpening;
use App\Models\StockWaste;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
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
        Blade::directive('quantity', fn (string $expression): string => "<?php echo \\App\\Support\\DisplayNumber::quantity($expression); ?>");
        Blade::directive('money', fn (string $expression): string => "<?php echo \\App\\Support\\DisplayNumber::money($expression); ?>");
        Gate::define('manage-salami-master-data', fn (User $user): bool => $user->isAdmin());
        Gate::define('reset-temporary-data', fn (User $user): bool => $user->isAdmin());
        Gate::define('register-salami-opening-stock', fn (User $user): bool => $user->isAdmin());
        Gate::define('operate-salami-receipts', fn (User $user): bool => $user->is_active);
        Gate::define('operate-salami-invoices', fn (User $user): bool => $user->is_active);
        Gate::define('cancel-salami-invoices', fn (User $user): bool => $user->isAdmin());
        Gate::define('register-salami-waste', fn (User $user): bool => $user->isAdmin());
        Gate::define('perform-salami-adjustments', fn (User $user): bool => $user->isAdmin());
        Gate::define('view-salami-reports', fn (User $user): bool => $user->is_active);
        Gate::define('manage-flower-master-data', fn (User $user): bool => $user->isAdmin());
        Gate::define('register-flower-opening-stock', fn (User $user): bool => $user->isAdmin());
        Gate::define('operate-flower-receipts', fn (User $user): bool => $user->is_active);
        Gate::define('register-flower-waste', fn (User $user): bool => $user->isAdmin());
        Gate::define('operate-flower-exits', fn (User $user): bool => $user->is_active);
        Gate::define('view-flower-inventory', fn (User $user): bool => $user->is_active);
        Gate::define('operate-flower-invoices', fn (User $user): bool => $user->is_active);
        Gate::define('cancel-flower-invoices', fn (User $user): bool => $user->isAdmin());
        Gate::define('view-flower-reports', fn (User $user): bool => $user->is_active);

        Relation::enforceMorphMap([
            'salami_product' => SalamiProduct::class,
            'flower_product' => FlowerProduct::class,
            'salami_receipt_item' => SalamiReceiptItem::class,
            'flower_receipt_item' => FlowerReceiptItem::class,
            'salami_invoice_item' => SalamiInvoiceItem::class,
            'flower_invoice_item' => FlowerInvoiceItem::class,
            'salami_invoice' => SalamiInvoice::class,
            'flower_invoice' => FlowerInvoice::class,
            'flower_exit' => FlowerExit::class,
            'stock_waste' => StockWaste::class,
            'stock_adjustment' => StockAdjustment::class,
            'stock_opening' => StockOpening::class,
        ]);
    }
}

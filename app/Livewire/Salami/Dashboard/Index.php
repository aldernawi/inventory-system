<?php

namespace App\Livewire\Salami\Dashboard;

use App\Enums\InvoiceStatus;
use App\Enums\MovementType;
use App\Enums\ReceiptStatus;
use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiInvoice;
use App\Models\SalamiProduct;
use App\Models\SalamiReceipt;
use App\Models\SalamiReceiptItem;
use App\Models\StockMovement;
use App\Models\StockWaste;
use App\Support\Quantity;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class Index extends Component
{
    use AuthorizesSalamiAccess;

    public function mount(): void
    {
        $this->authorizeSalamiInvoices();
    }

    public function render(): View
    {
        $today = today();
        $stockableType = (new SalamiProduct)->getMorphClass();
        $movementQuery = StockMovement::query()->where('stockable_type', $stockableType)->whereDate('occurred_at', $today);
        $confirmedInvoicesToday = SalamiInvoice::query()
            ->where('status', InvoiceStatus::Confirmed)
            ->whereDate('invoice_date', $today);

        return view('livewire.salami.dashboard.index', [
            'activeProductCount' => SalamiProduct::query()->where('is_active', true)->count(),
            'invoiceCountToday' => (clone $confirmedInvoicesToday)->count(),
            'latestInvoices' => SalamiInvoice::query()->with('customer')->latest('invoice_date')->latest('id')->limit(5)->get(),
            'latestReceipts' => SalamiReceipt::query()->with('supplier')->latest('receipt_date')->latest('id')->limit(5)->get(),
            'lowStockProducts' => SalamiProduct::query()
                ->where(function (Builder $query): void {
                    $query->where('current_quantity', '0')
                        ->orWhere(function (Builder $query): void {
                            $query->where('current_quantity', '>', '0')
                                ->whereNotNull('minimum_quantity')
                                ->whereColumn('current_quantity', '<=', 'minimum_quantity');
                        });
                })
                ->orderBy('current_quantity')
                ->limit(5)
                ->get(),
            'lowStockProductCount' => SalamiProduct::query()
                ->where(function (Builder $query): void {
                    $query->where('current_quantity', '0')
                        ->orWhere(function (Builder $query): void {
                            $query->where('current_quantity', '>', '0')
                                ->whereNotNull('minimum_quantity')
                                ->whereColumn('current_quantity', '<=', 'minimum_quantity');
                        });
                })
                ->count(),
            'quantityReceivedToday' => $this->sumAttribute((clone $movementQuery)->where('movement_type', MovementType::Receipt), 'quantity'),
            'quantitySoldToday' => $this->sumAttribute((clone $movementQuery)->where('movement_type', MovementType::Sale), 'quantity', absolute: true),
            'salesValueToday' => $this->sumAttribute($confirmedInvoicesToday, 'total_amount'),
            'shortageToday' => $this->sumAttribute(
                SalamiReceiptItem::query()
                    ->whereHas('receipt', fn (Builder $query) => $query->where('status', ReceiptStatus::Confirmed)->whereDate('receipt_date', $today)),
                'shortage_quantity',
            ),
            'totalCurrentQuantity' => $this->sumAttribute(SalamiProduct::query()->where('is_active', true), 'current_quantity'),
            'wasteToday' => $this->sumAttribute(
                StockWaste::query()->where('stockable_type', $stockableType)->whereDate('waste_date', $today),
                'quantity',
            ),
        ]);
    }

    private function sumAttribute(Builder $query, string $attribute, bool $absolute = false): string
    {
        return $query->cursor()->reduce(
            function (Quantity $total, object $record) use ($attribute, $absolute): Quantity {
                $value = Quantity::from($record->{$attribute});

                return $total->plus($absolute ? $value->absolute() : $value);
            },
            Quantity::zero(),
        )->toString();
    }
}

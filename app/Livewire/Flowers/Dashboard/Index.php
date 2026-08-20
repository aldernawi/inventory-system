<?php

namespace App\Livewire\Flowers\Dashboard;

use App\Enums\MovementType;
use App\Enums\ReceiptStatus;
use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerExit;
use App\Models\FlowerInvoice;
use App\Models\FlowerProduct;
use App\Models\FlowerReceipt;
use App\Models\FlowerReceiptItem;
use App\Models\StockMovement;
use App\Models\StockWaste;
use App\Support\Quantity;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class Index extends Component
{
    use AuthorizesFlowerAccess;

    public function mount(): void
    {
        $this->authorizeFlowerInventory();
    }

    public function render(): View
    {
        $today = today();
        $stockableType = (new FlowerProduct)->getMorphClass();
        $movementQuery = StockMovement::query()->where('stockable_type', $stockableType)->whereDate('occurred_at', $today);
        $confirmedReceiptItemsToday = FlowerReceiptItem::query()->whereHas('receipt', fn (Builder $query) => $query->where('status', ReceiptStatus::Confirmed)->whereDate('receipt_date', $today));

        return view('livewire.flowers.dashboard.index', [
            'activeProductCount' => FlowerProduct::query()->where('is_active', true)->count(),
            'totalCurrentQuantity' => $this->sumAttribute(FlowerProduct::query(), 'current_quantity'),
            'quantityReceivedToday' => $this->sumAttribute((clone $movementQuery)->where('movement_type', MovementType::Receipt), 'quantity'),
            'acceptedQuantityToday' => $this->sumAttribute($confirmedReceiptItemsToday, 'accepted_quantity'),
            'shortageToday' => $this->sumAttribute((clone $confirmedReceiptItemsToday), 'shortage_quantity'),
            'arrivalDamageToday' => $this->sumAttribute((clone $confirmedReceiptItemsToday), 'damaged_quantity'),
            'wasteToday' => $this->sumAttribute((clone $movementQuery)->where('movement_type', MovementType::Waste), 'quantity', absolute: true),
            'manualExitsToday' => $this->sumAttribute((clone $movementQuery)->where('movement_type', MovementType::ManualExit), 'quantity', absolute: true),
            'quantitySoldToday' => $this->sumAttribute((clone $movementQuery)->where('movement_type', MovementType::Sale), 'quantity', absolute: true),
            'invoiceCountToday' => FlowerInvoice::query()->where('status', 'confirmed')->whereDate('invoice_date', $today)->count(),
            'salesValueToday' => $this->sumAttribute(FlowerInvoice::query()->where('status', 'confirmed')->whereDate('invoice_date', $today), 'total_amount'),
            'lowStockProductCount' => $this->lowStockQuery()->count(),
            'lowStockProducts' => $this->lowStockQuery()->orderBy('current_quantity')->limit(5)->get(),
            'latestReceipts' => FlowerReceipt::query()->with('supplier')->latest('receipt_date')->latest('id')->limit(5)->get(),
            'latestWastes' => StockWaste::query()->with(['stockable', 'createdBy'])->where('stockable_type', $stockableType)->latest('waste_date')->latest('id')->limit(5)->get(),
            'latestExits' => FlowerExit::query()->with(['product', 'createdBy'])->latest('exit_date')->latest('id')->limit(5)->get(),
            'latestInvoices' => FlowerInvoice::query()->with('createdBy')->latest('invoice_date')->latest('id')->limit(5)->get(),
        ]);
    }

    private function lowStockQuery(): Builder
    {
        return FlowerProduct::query()->where(function (Builder $query): void {
            $query->where('current_quantity', '0')->orWhere(function (Builder $query): void {
                $query->where('current_quantity', '>', '0')->whereNotNull('minimum_quantity')->whereColumn('current_quantity', '<=', 'minimum_quantity');
            });
        });
    }

    private function sumAttribute(Builder $query, string $attribute, bool $absolute = false): string
    {
        return $query->cursor()->reduce(function (Quantity $total, $model) use ($attribute, $absolute): Quantity {
            $quantity = Quantity::from($model->{$attribute});

            return $total->plus($absolute ? $quantity->absolute() : $quantity);
        }, Quantity::zero())->toString();
    }
}

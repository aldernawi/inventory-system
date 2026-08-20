<?php

namespace App\Livewire\Flowers\Inventory;

use App\Enums\ReceiptStatus;
use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerProduct;
use App\Models\FlowerReceiptItem;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ReceiptAge extends Component
{
    use AuthorizesFlowerAccess, WithPagination;

    public FlowerProduct $product;

    public function mount(FlowerProduct $product): void
    {
        $this->authorizeFlowerInventory();
        $this->product = $product;
    }

    public function render(): View
    {
        $arrivals = FlowerReceiptItem::query()->with('receipt.supplier')
            ->where('flower_product_id', $this->product->getKey())
            ->whereHas('receipt', fn ($query) => $query->where('status', ReceiptStatus::Confirmed))
            ->join('flower_receipts', 'flower_receipt_items.receipt_id', '=', 'flower_receipts.id')
            ->orderByDesc('flower_receipts.receipt_date')
            ->orderByDesc('flower_receipt_items.id')
            ->select('flower_receipt_items.*')
            ->paginate(20);

        return view('livewire.flowers.inventory.receipt-age', ['product' => $this->product->fresh(), 'arrivals' => $arrivals]);
    }
}

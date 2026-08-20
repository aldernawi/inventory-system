<?php

namespace App\Livewire\Flowers\Receipts;

use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerReceipt;
use App\Models\Supplier;
use App\Support\Quantity;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesFlowerAccess, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $supplierId = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->authorizeFlowerInventory();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'supplierId', 'status', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function total(FlowerReceipt $receipt, string $attribute): string
    {
        return $receipt->items->reduce(
            fn (Quantity $total, $item): Quantity => $total->plus(Quantity::from($item->{$attribute})),
            Quantity::zero(),
        )->toString();
    }

    public function render(): View
    {
        $receipts = FlowerReceipt::query()
            ->with(['supplier', 'items', 'createdBy'])
            ->when($this->search !== '', fn ($query) => $query->where('receipt_number', 'like', "%{$this->search}%"))
            ->when($this->supplierId !== '', fn ($query) => $query->where('supplier_id', $this->supplierId))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->from !== '', fn ($query) => $query->whereDate('receipt_date', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('receipt_date', '<=', $this->to))
            ->latest('receipt_date')->latest('id')->paginate(15);

        return view('livewire.flowers.receipts.index', [
            'receipts' => $receipts,
            'suppliers' => Supplier::query()->whereIn('module_scope', ['flower', 'both'])->orderBy('name')->get(['id', 'name']),
        ]);
    }
}

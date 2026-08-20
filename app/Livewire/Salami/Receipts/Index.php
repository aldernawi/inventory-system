<?php

namespace App\Livewire\Salami\Receipts;

use App\Models\SalamiReceipt;
use App\Support\Quantity;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

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

    public function updated($property): void
    {
        if (in_array($property, ['search', 'supplierId', 'status', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function total(SalamiReceipt $receipt, string $attribute): string
    {
        return $receipt->items->reduce(
            fn (Quantity $total, $item): Quantity => $total->plus(Quantity::from($item->{$attribute})),
            Quantity::zero(),
        )->toString();
    }

    public function render(): View
    {
        $receipts = SalamiReceipt::query()
            ->with(['supplier', 'items', 'createdBy'])
            ->when($this->search !== '', fn ($query) => $query->where('receipt_number', 'like', "%{$this->search}%"))
            ->when($this->supplierId !== '', fn ($query) => $query->where('supplier_id', $this->supplierId))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->from !== '', fn ($query) => $query->whereDate('receipt_date', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('receipt_date', '<=', $this->to))
            ->latest('receipt_date')
            ->latest('id')
            ->paginate(15);

        return view('livewire.salami.receipts.index', compact('receipts'));
    }
}

<?php

namespace App\Livewire\Flowers\Waste;

use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerProduct;
use App\Models\StockWaste;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesFlowerAccess, WithPagination;

    #[Url]
    public string $productId = '';

    #[Url]
    public string $reason = '';

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
        if (in_array($property, ['productId', 'reason', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $wastes = StockWaste::query()->with(['stockable', 'createdBy'])
            ->where('stockable_type', (new FlowerProduct)->getMorphClass())
            ->when($this->productId !== '', fn ($query) => $query->where('stockable_id', $this->productId))
            ->when($this->reason !== '', fn ($query) => $query->where('reason', 'like', "%{$this->reason}%"))
            ->when($this->from !== '', fn ($query) => $query->whereDate('waste_date', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('waste_date', '<=', $this->to))
            ->latest('waste_date')->latest('id')->paginate(15);

        return view('livewire.flowers.waste.index', ['products' => FlowerProduct::query()->orderBy('name')->get(['id', 'name']), 'wastes' => $wastes]);
    }
}

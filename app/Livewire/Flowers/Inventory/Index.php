<?php

namespace App\Livewire\Flowers\Inventory;

use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerProduct;
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
    public string $stockStatus = 'all';

    #[Url]
    public string $sort = 'name';

    #[Url]
    public string $direction = 'asc';

    public function mount(): void
    {
        $this->authorizeFlowerInventory();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStockStatus(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, ['name', 'code', 'current_quantity', 'minimum_quantity', 'purchase_price', 'sale_price'], true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
        $this->resetPage();
    }

    public function statusFor(FlowerProduct $product): string
    {
        $current = Quantity::from($product->current_quantity);

        if ($current->isZero()) {
            return 'out';
        }

        if ($product->minimum_quantity !== null && ! $current->isGreaterThan(Quantity::from($product->minimum_quantity))) {
            return 'low';
        }

        return 'available';
    }

    public function render(): View
    {
        $direction = $this->direction === 'desc' ? 'desc' : 'asc';
        $products = FlowerProduct::query()
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('company_name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                        ->orWhere('color', 'like', "%{$this->search}%")
                        ->orWhere('grade', 'like', "%{$this->search}%");
                });
            })
            ->when($this->stockStatus === 'out', fn ($query) => $query->where('current_quantity', '0'))
            ->when($this->stockStatus === 'low', fn ($query) => $query->where('current_quantity', '>', '0')->whereColumn('current_quantity', '<=', 'minimum_quantity'))
            ->when($this->stockStatus === 'available', function ($query): void {
                $query->where('current_quantity', '>', '0')->where(function ($query): void {
                    $query->whereNull('minimum_quantity')->orWhereColumn('current_quantity', '>', 'minimum_quantity');
                });
            })
            ->orderBy($this->sort, $direction)->paginate(15);

        return view('livewire.flowers.inventory.index', compact('products'));
    }
}

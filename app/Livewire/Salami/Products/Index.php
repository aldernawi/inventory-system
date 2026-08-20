<?php

namespace App\Livewire\Salami\Products;

use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiProduct;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesSalamiAccess, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $productId): void
    {
        $this->authorizeSalamiMasterData();
        $product = SalamiProduct::query()->findOrFail($productId);

        $product->update([
            'is_active' => ! $product->is_active,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('status', 'تم تحديث حالة الصنف.');
    }

    public function render(): View
    {
        $products = SalamiProduct::query()
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%");
                });
            })
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.salami.products.index', compact('products'));
    }
}

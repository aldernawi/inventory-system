<?php

namespace App\Livewire\Salami\Adjustments;

use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiProduct;
use App\Models\StockAdjustment;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesSalamiAccess;
    use WithPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $productId = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->authorizeSalamiAdjustments();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['productId', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $adjustments = StockAdjustment::query()
            ->with(['stockable', 'createdBy'])
            ->where('stockable_type', (new SalamiProduct)->getMorphClass())
            ->when($this->productId !== '', fn ($query) => $query->where('stockable_id', $this->productId))
            ->when($this->from !== '', fn ($query) => $query->whereDate('adjustment_date', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('adjustment_date', '<=', $this->to))
            ->latest('adjustment_date')
            ->latest('id')
            ->paginate(15);

        return view('livewire.salami.adjustments.index', [
            'adjustments' => $adjustments,
            'products' => SalamiProduct::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}

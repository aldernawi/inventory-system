<?php

namespace App\Livewire\Salami\Waste;

use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiProduct;
use App\Models\StockWaste;
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
    public string $reason = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->authorizeSalamiReports();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['productId', 'reason', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $wastes = StockWaste::query()
            ->with(['stockable', 'createdBy'])
            ->where('stockable_type', (new SalamiProduct)->getMorphClass())
            ->when($this->productId !== '', fn ($query) => $query->where('stockable_id', $this->productId))
            ->when($this->reason !== '', fn ($query) => $query->where('reason', 'like', "%{$this->reason}%"))
            ->when($this->from !== '', fn ($query) => $query->whereDate('waste_date', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('waste_date', '<=', $this->to))
            ->latest('waste_date')
            ->latest('id')
            ->paginate(15);

        return view('livewire.salami.waste.index', [
            'products' => SalamiProduct::query()->orderBy('name')->get(['id', 'name']),
            'wastes' => $wastes,
        ]);
    }
}

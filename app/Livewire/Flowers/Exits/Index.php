<?php

namespace App\Livewire\Flowers\Exits;

use App\Enums\FlowerExitType;
use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerExit;
use App\Models\FlowerProduct;
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
    public string $exitType = '';

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
        if (in_array($property, ['productId', 'exitType', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function exitTypeLabel(FlowerExitType $type): string
    {
        return match ($type) {
            FlowerExitType::Gift => 'هدية', FlowerExitType::InternalUse => 'استخدام داخلي', FlowerExitType::Sample => 'عينة', FlowerExitType::Other => 'أخرى',
        };
    }

    public function render(): View
    {
        $exits = FlowerExit::query()->with(['product', 'createdBy'])
            ->when($this->productId !== '', fn ($query) => $query->where('flower_product_id', $this->productId))
            ->when($this->exitType !== '', fn ($query) => $query->where('exit_type', $this->exitType))
            ->when($this->from !== '', fn ($query) => $query->whereDate('exit_date', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('exit_date', '<=', $this->to))
            ->latest('exit_date')->latest('id')->paginate(15);

        return view('livewire.flowers.exits.index', ['products' => FlowerProduct::query()->orderBy('name')->get(['id', 'name']), 'exits' => $exits, 'exitTypes' => FlowerExitType::cases()]);
    }
}

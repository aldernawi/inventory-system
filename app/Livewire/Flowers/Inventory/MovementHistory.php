<?php

namespace App\Livewire\Flowers\Inventory;

use App\Enums\MovementType;
use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerProduct;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class MovementHistory extends Component
{
    use AuthorizesFlowerAccess, WithPagination;

    public FlowerProduct $product;

    #[Url]
    public string $movementType = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(FlowerProduct $product): void
    {
        $this->authorizeFlowerInventory();
        $this->product = $product;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['movementType', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function movementLabel(MovementType $movementType): string
    {
        return match ($movementType) {
            MovementType::Opening => 'رصيد افتتاحي',
            MovementType::Receipt => 'استلام',
            MovementType::Sale => 'بيع',
            MovementType::ManualExit => 'خروج يدوي',
            MovementType::Waste => 'تالف',
            MovementType::AdjustmentIn => 'تسوية زيادة',
            MovementType::AdjustmentOut => 'تسوية نقص',
            MovementType::Reversal => 'عكس حركة',
        };
    }

    public function render(): View
    {
        $movements = $this->product->stockMovements()->with(['createdBy', 'reference'])
            ->when($this->movementType !== '', fn ($query) => $query->where('movement_type', $this->movementType))
            ->when($this->from !== '', fn ($query) => $query->whereDate('occurred_at', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('occurred_at', '<=', $this->to))
            ->latest('occurred_at')->latest('id')->paginate(20);

        return view('livewire.flowers.inventory.movement-history', ['product' => $this->product->fresh(), 'movements' => $movements, 'movementTypes' => MovementType::cases()]);
    }
}

<?php

namespace App\Livewire\Flowers\Products;

use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerProduct;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    use AuthorizesFlowerAccess;

    public FlowerProduct $product;

    public function mount(FlowerProduct $product): void
    {
        $this->authorizeFlowerInventory();
        $this->product = $product;
    }

    public function render(): View
    {
        return view('livewire.flowers.products.show', ['product' => $this->product->fresh()]);
    }
}

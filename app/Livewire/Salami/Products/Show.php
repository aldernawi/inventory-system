<?php

namespace App\Livewire\Salami\Products;

use App\Models\SalamiProduct;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public SalamiProduct $product;

    public function mount(SalamiProduct $product): void
    {
        $this->product = $product;
    }

    public function render(): View
    {
        return view('livewire.salami.products.show', [
            'product' => $this->product->fresh(),
        ]);
    }
}

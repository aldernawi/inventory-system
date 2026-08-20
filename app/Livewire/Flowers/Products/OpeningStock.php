<?php

namespace App\Livewire\Flowers\Products;

use App\Exceptions\Inventory\StockMutationException;
use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerProduct;
use App\Services\Inventory\StockService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class OpeningStock extends Component
{
    use AuthorizesFlowerAccess;

    public FlowerProduct $product;

    public string $quantity = '';

    public string $notes = '';

    public function mount(FlowerProduct $product): void
    {
        $this->authorizeFlowerOpeningStock();
        $this->product = $product;
    }

    public function register(StockService $stockService): mixed
    {
        $this->authorizeFlowerOpeningStock();
        $validated = $this->validate([
            'quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $stockService->opening($this->product, $validated['quantity'], auth()->user(), trim($validated['notes']) ?: null);
        } catch (StockMutationException $exception) {
            $this->addError('quantity', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تم تسجيل الرصيد الافتتاحي بنجاح.');

        return $this->redirectRoute('flowers.products.show', ['product' => $this->product], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.flowers.products.opening-stock', ['product' => $this->product->fresh()]);
    }
}

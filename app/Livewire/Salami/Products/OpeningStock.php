<?php

namespace App\Livewire\Salami\Products;

use App\Exceptions\Inventory\StockMutationException;
use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiProduct;
use App\Services\Inventory\StockService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class OpeningStock extends Component
{
    use AuthorizesSalamiAccess;

    public SalamiProduct $product;

    public string $quantity = '';

    public string $notes = '';

    public function mount(SalamiProduct $product): void
    {
        $this->authorizeSalamiOpeningStock();
        $this->product = $product;
    }

    public function register(StockService $stockService): mixed
    {
        $this->authorizeSalamiOpeningStock();
        $validated = $this->validate([
            'quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $stockService->opening(
                $this->product,
                $validated['quantity'],
                auth()->user(),
                trim($validated['notes']) ?: null,
            );
        } catch (StockMutationException $exception) {
            $this->addError('quantity', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تم تسجيل الرصيد الافتتاحي بنجاح.');

        return $this->redirectRoute('salami.products.show', ['product' => $this->product], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.salami.products.opening-stock', [
            'product' => $this->product->fresh(),
        ]);
    }
}

<?php

namespace App\Livewire\Flowers\Waste;

use App\Exceptions\Flowers\StockOperationException;
use App\Exceptions\Inventory\InvalidStockQuantityException;
use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerProduct;
use App\Services\Flowers\WasteService;
use App\Support\Quantity;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesFlowerAccess;

    public string $productId = '';

    public string $quantity = '1.000';

    public string $reason = '';

    public string $wasteDate = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorizeFlowerWaste();
        $this->wasteDate = today()->toDateString();
    }

    /** @return array{current: string, quantity: string, after: string, valid: bool} */
    public function preview(): array
    {
        $product = is_numeric($this->productId) ? FlowerProduct::query()->find((int) $this->productId) : null;

        if (! $product instanceof FlowerProduct) {
            return ['current' => '—', 'quantity' => '0.000', 'after' => '—', 'valid' => false];
        }

        try {
            $quantity = Quantity::from($this->quantity === '' ? '0' : $this->quantity);
            $current = Quantity::from($product->current_quantity);

            return ['current' => $current->toString(), 'quantity' => $quantity->toString(), 'after' => $current->minus($quantity)->toString(), 'valid' => $quantity->isPositive() && ! $quantity->isGreaterThan($current)];
        } catch (InvalidStockQuantityException) {
            return ['current' => $product->current_quantity, 'quantity' => '0.000', 'after' => '—', 'valid' => false];
        }
    }

    public function record(WasteService $wasteService): mixed
    {
        $this->authorizeFlowerWaste();
        $validated = $this->validate();

        try {
            $wasteService->record([
                'flower_product_id' => $validated['productId'], 'quantity' => $validated['quantity'], 'reason' => $validated['reason'],
                'waste_date' => $validated['wasteDate'], 'notes' => $validated['notes'],
            ], auth()->user());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return null;
        } catch (InvalidStockQuantityException|StockOperationException $exception) {
            $this->addError('waste', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تم تسجيل تالف الورد وخصم الكمية من المخزون.');

        return $this->redirectRoute('flowers.waste.index', navigate: true);
    }

    protected function rules(): array
    {
        return [
            'productId' => ['required', 'integer', 'exists:flower_products,id'],
            'quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'reason' => ['required', 'string', 'max:255'],
            'wasteDate' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function render(): View
    {
        return view('livewire.flowers.waste.form', ['products' => FlowerProduct::query()->where('is_active', true)->orderBy('name')->get()]);
    }
}

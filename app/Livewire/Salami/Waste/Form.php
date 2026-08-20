<?php

namespace App\Livewire\Salami\Waste;

use App\Exceptions\Inventory\InvalidStockQuantityException;
use App\Exceptions\Salami\StockOperationException;
use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiProduct;
use App\Services\Salami\WasteService;
use App\Support\Quantity;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesSalamiAccess;

    public string $notes = '';

    public string $productId = '';

    public string $quantity = '1.000';

    public string $reason = '';

    public string $wasteDate = '';

    public function mount(): void
    {
        $this->authorizeSalamiWaste();
        $this->wasteDate = today()->toDateString();
    }

    public function preview(): array
    {
        $product = is_numeric($this->productId)
            ? SalamiProduct::query()->find((int) $this->productId)
            : null;

        if (! $product instanceof SalamiProduct) {
            return ['current' => '—', 'quantity' => '0.000', 'after' => '—', 'valid' => false];
        }

        try {
            $quantity = Quantity::from($this->quantity === '' ? '0' : $this->quantity);
            $current = Quantity::from($product->current_quantity);

            return [
                'current' => $current->toString(),
                'quantity' => $quantity->toString(),
                'after' => $current->minus($quantity)->toString(),
                'valid' => $quantity->isPositive() && ! $quantity->isGreaterThan($current),
            ];
        } catch (InvalidStockQuantityException) {
            return ['current' => $product->current_quantity, 'quantity' => '0.000', 'after' => '—', 'valid' => false];
        }
    }

    public function record(WasteService $wasteService): mixed
    {
        $this->authorizeSalamiWaste();
        $validated = $this->validate();

        try {
            $wasteService->record([
                'product_id' => $validated['productId'],
                'quantity' => $validated['quantity'],
                'reason' => $validated['reason'],
                'waste_date' => $validated['wasteDate'],
                'notes' => $validated['notes'],
            ], auth()->user());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return null;
        } catch (InvalidStockQuantityException|StockOperationException $exception) {
            $this->addError('waste', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تم تسجيل التالف وخصم الكمية من المخزون.');

        return $this->redirectRoute('salami.waste.index', navigate: true);
    }

    protected function rules(): array
    {
        return [
            'productId' => ['required', 'integer', 'exists:salami_products,id'],
            'quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'reason' => ['required', 'string', 'max:255'],
            'wasteDate' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function render(): View
    {
        return view('livewire.salami.waste.form', [
            'products' => SalamiProduct::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}

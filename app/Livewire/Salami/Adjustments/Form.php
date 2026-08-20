<?php

namespace App\Livewire\Salami\Adjustments;

use App\Exceptions\Inventory\InvalidStockQuantityException;
use App\Exceptions\Salami\StockOperationException;
use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiProduct;
use App\Services\Salami\AdjustmentService;
use App\Support\Quantity;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesSalamiAccess;

    public string $actualQuantity = '';

    public string $adjustmentDate = '';

    public string $notes = '';

    public string $productId = '';

    public string $reason = '';

    public function mount(): void
    {
        $this->authorizeSalamiAdjustments();
        $this->adjustmentDate = today()->toDateString();
    }

    public function preview(): array
    {
        $product = is_numeric($this->productId)
            ? SalamiProduct::query()->find((int) $this->productId)
            : null;

        if (! $product instanceof SalamiProduct) {
            return ['system' => '—', 'actual' => '—', 'difference' => '—', 'valid' => false];
        }

        try {
            $system = Quantity::from($product->current_quantity);
            $actual = Quantity::from($this->actualQuantity === '' ? '0' : $this->actualQuantity);

            return [
                'system' => $system->toString(),
                'actual' => $actual->toString(),
                'difference' => $actual->minus($system)->toString(),
                'valid' => ! $actual->isNegative() && ! $actual->isEqualTo($system),
            ];
        } catch (InvalidStockQuantityException) {
            return ['system' => $product->current_quantity, 'actual' => '—', 'difference' => '—', 'valid' => false];
        }
    }

    public function record(AdjustmentService $adjustmentService): mixed
    {
        $this->authorizeSalamiAdjustments();
        $validated = $this->validate();

        try {
            $adjustmentService->record([
                'product_id' => $validated['productId'],
                'actual_quantity' => $validated['actualQuantity'],
                'adjustment_date' => $validated['adjustmentDate'],
                'reason' => $validated['reason'],
                'notes' => $validated['notes'],
            ], auth()->user());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return null;
        } catch (InvalidStockQuantityException|StockOperationException $exception) {
            $this->addError('adjustment', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تمت تسوية المخزون وتسجيل حركة التدقيق.');

        return $this->redirectRoute('salami.adjustments.index', navigate: true);
    }

    protected function rules(): array
    {
        return [
            'productId' => ['required', 'integer', 'exists:salami_products,id'],
            'actualQuantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'adjustmentDate' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function render(): View
    {
        return view('livewire.salami.adjustments.form', [
            'products' => SalamiProduct::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}

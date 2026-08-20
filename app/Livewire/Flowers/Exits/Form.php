<?php

namespace App\Livewire\Flowers\Exits;

use App\Enums\FlowerExitType;
use App\Exceptions\Flowers\StockOperationException;
use App\Exceptions\Inventory\InvalidStockQuantityException;
use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerProduct;
use App\Services\Flowers\ExitService;
use App\Support\Quantity;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesFlowerAccess;

    public string $productId = '';

    public string $quantity = '1.000';

    public string $exitDate = '';

    public string $exitType = 'gift';

    public string $recipientName = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorizeFlowerExits();
        $this->exitDate = today()->toDateString();
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

    public function record(ExitService $exitService): mixed
    {
        $this->authorizeFlowerExits();
        $validated = $this->validate();

        try {
            $exitService->record([
                'flower_product_id' => $validated['productId'], 'quantity' => $validated['quantity'], 'exit_date' => $validated['exitDate'],
                'exit_type' => $validated['exitType'], 'recipient_name' => $validated['recipientName'], 'notes' => $validated['notes'],
            ], auth()->user());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return null;
        } catch (InvalidStockQuantityException|StockOperationException $exception) {
            $this->addError('exit', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تم تسجيل خروج الورد وخصم الكمية من المخزون.');

        return $this->redirectRoute('flowers.exits.index', navigate: true);
    }

    protected function rules(): array
    {
        return [
            'productId' => ['required', 'integer', 'exists:flower_products,id'],
            'quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'exitDate' => ['required', 'date'],
            'exitType' => ['required', Rule::in(FlowerExitType::values())],
            'recipientName' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function exitTypeLabel(FlowerExitType $type): string
    {
        return match ($type) {
            FlowerExitType::Gift => 'هدية', FlowerExitType::InternalUse => 'استخدام داخلي', FlowerExitType::Sample => 'عينة', FlowerExitType::Other => 'أخرى',
        };
    }

    public function render(): View
    {
        return view('livewire.flowers.exits.form', ['products' => FlowerProduct::query()->where('is_active', true)->orderBy('name')->get(), 'exitTypes' => FlowerExitType::cases()]);
    }
}

<?php

namespace App\Livewire\Salami\Products;

use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiProduct;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesSalamiAccess;

    public ?SalamiProduct $product = null;

    public string $name = '';

    public string $code = '';

    public string $unit = 'قطعة';

    public string $purchasePrice = '';

    public string $salePrice = '';

    public string $minimumQuantity = '0';

    public string $notes = '';

    public bool $isActive = true;

    public function mount(?SalamiProduct $product = null): void
    {
        $this->authorizeSalamiMasterData();
        $this->product = $product;

        if ($product instanceof SalamiProduct) {
            $this->name = $product->name;
            $this->code = $product->code;
            $this->unit = $product->unit;
            $this->purchasePrice = $product->purchase_price ?? '';
            $this->salePrice = $product->sale_price ?? '';
            $this->minimumQuantity = $product->minimum_quantity;
            $this->notes = $product->notes ?? '';
            $this->isActive = $product->is_active;
        }
    }

    public function save(): mixed
    {
        $this->authorizeSalamiMasterData();
        $validated = $this->validate();

        $attributes = [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'unit' => $validated['unit'],
            'purchase_price' => $this->nullableDecimal($validated['purchasePrice']),
            'sale_price' => $this->nullableDecimal($validated['salePrice']),
            'minimum_quantity' => $validated['minimumQuantity'],
            'notes' => $this->nullableText($validated['notes']),
            'is_active' => $validated['isActive'],
        ];

        if ($this->product instanceof SalamiProduct) {
            $this->product->update([...$attributes, 'updated_by' => auth()->id()]);
            session()->flash('status', 'تم تحديث بيانات الصنف.');
        } else {
            $this->product = SalamiProduct::query()->create([
                ...$attributes,
                'created_by' => auth()->id(),
            ]);
            session()->flash('status', 'تم إنشاء الصنف ورصيده الافتتاحي صفر.');
        }

        return $this->redirectRoute('salami.products.show', ['product' => $this->product], navigate: true);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', Rule::unique('salami_products', 'code')->ignore($this->product)],
            'unit' => ['required', 'string', 'max:50'],
            'purchasePrice' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'salePrice' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'minimumQuantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'notes' => ['nullable', 'string'],
            'isActive' => ['boolean'],
        ];
    }

    private function nullableText(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function nullableDecimal(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    public function render(): View
    {
        return view('livewire.salami.products.form');
    }
}

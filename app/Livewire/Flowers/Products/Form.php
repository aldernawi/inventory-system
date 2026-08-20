<?php

namespace App\Livewire\Flowers\Products;

use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerProduct;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesFlowerAccess;

    public ?FlowerProduct $product = null;

    public string $name = '';

    public string $code = '';

    public string $color = '';

    public string $grade = '';

    public string $unit = '';

    public string $purchasePrice = '';

    public string $salePrice = '';

    public string $minimumQuantity = '';

    public string $notes = '';

    public bool $isActive = true;

    public function mount(?FlowerProduct $product = null): void
    {
        $this->authorizeFlowerMasterData();
        $this->product = $product;

        if ($product instanceof FlowerProduct) {
            $this->name = $product->name;
            $this->code = $product->code;
            $this->color = $product->color ?? '';
            $this->grade = $product->grade ?? '';
            $this->unit = $product->unit;
            $this->purchasePrice = $product->purchase_price ?? '';
            $this->salePrice = $product->sale_price ?? '';
            $this->minimumQuantity = $product->minimum_quantity ?? '';
            $this->notes = $product->notes ?? '';
            $this->isActive = $product->is_active;
        }
    }

    public function save(): mixed
    {
        $this->authorizeFlowerMasterData();
        $validated = $this->validate();
        $attributes = [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'color' => $this->nullableText($validated['color']),
            'grade' => $this->nullableText($validated['grade']),
            'unit' => $validated['unit'],
            'purchase_price' => $this->nullableDecimal($validated['purchasePrice']),
            'sale_price' => $this->nullableDecimal($validated['salePrice']),
            'minimum_quantity' => $this->nullableDecimal($validated['minimumQuantity']),
            'notes' => $this->nullableText($validated['notes']),
            'is_active' => $validated['isActive'],
        ];

        if ($this->product instanceof FlowerProduct) {
            $this->product->update([...$attributes, 'updated_by' => auth()->id()]);
            session()->flash('status', 'تم تحديث بيانات نوع الورد.');
        } else {
            $this->product = FlowerProduct::query()->create([...$attributes, 'created_by' => auth()->id()]);
            session()->flash('status', 'تم إنشاء نوع الورد برصيد صفري.');
        }

        return $this->redirectRoute('flowers.products.show', ['product' => $this->product], navigate: true);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', Rule::unique('flower_products', 'code')->ignore($this->product)],
            'color' => ['nullable', 'string', 'max:100'],
            'grade' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:50'],
            'purchasePrice' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'salePrice' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'minimumQuantity' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
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
        return view('livewire.flowers.products.form');
    }
}

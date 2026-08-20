<?php

namespace App\Livewire\Flowers\Suppliers;

use App\Enums\SupplierScope;
use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesFlowerAccess;

    public ?Supplier $supplier = null;

    public string $name = '';

    public string $companyName = '';

    public string $phone = '';

    public string $country = '';

    public string $moduleScope = 'flower';

    public string $notes = '';

    public bool $isActive = true;

    public function mount(?Supplier $supplier = null): void
    {
        $this->authorizeFlowerMasterData();

        if ($supplier instanceof Supplier && ! in_array($supplier->module_scope, [SupplierScope::Flower, SupplierScope::Both], true)) {
            abort(404);
        }

        $this->supplier = $supplier;

        if ($supplier instanceof Supplier) {
            $this->name = $supplier->name;
            $this->companyName = $supplier->company_name ?? '';
            $this->phone = $supplier->phone ?? '';
            $this->country = $supplier->country ?? '';
            $this->moduleScope = $supplier->module_scope->value;
            $this->notes = $supplier->notes ?? '';
            $this->isActive = $supplier->is_active;
        }
    }

    public function save(): mixed
    {
        $this->authorizeFlowerMasterData();
        $validated = $this->validate();
        $attributes = [
            'name' => $validated['name'],
            'company_name' => $this->nullableText($validated['companyName']),
            'phone' => $this->nullableText($validated['phone']),
            'country' => $this->nullableText($validated['country']),
            'module_scope' => $validated['moduleScope'],
            'notes' => $this->nullableText($validated['notes']),
            'is_active' => $validated['isActive'],
        ];

        if ($this->supplier instanceof Supplier) {
            $this->supplier->update([...$attributes, 'updated_by' => auth()->id()]);
            session()->flash('status', 'تم تحديث بيانات مورد الورد.');
        } else {
            $this->supplier = Supplier::query()->create([...$attributes, 'created_by' => auth()->id()]);
            session()->flash('status', 'تم إنشاء مورد الورد.');
        }

        return $this->redirectRoute('flowers.suppliers.index', navigate: true);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'companyName' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'moduleScope' => ['required', Rule::in([SupplierScope::Flower->value, SupplierScope::Both->value])],
            'notes' => ['nullable', 'string'],
            'isActive' => ['boolean'],
        ];
    }

    private function nullableText(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function render(): View
    {
        return view('livewire.flowers.suppliers.form');
    }
}

<?php

namespace App\Livewire\Salami\Customers;

use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiCustomer;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesSalamiAccess;

    public ?SalamiCustomer $customer = null;

    public string $name = '';

    public string $contactPerson = '';

    public string $phone = '';

    public string $area = '';

    public string $address = '';

    public string $notes = '';

    public bool $isActive = true;

    public function mount(?SalamiCustomer $customer = null): void
    {
        $this->authorizeSalamiMasterData();
        $this->customer = $customer;

        if ($customer instanceof SalamiCustomer) {
            $this->name = $customer->name;
            $this->contactPerson = $customer->contact_person ?? '';
            $this->phone = $customer->phone ?? '';
            $this->area = $customer->area ?? '';
            $this->address = $customer->address ?? '';
            $this->notes = $customer->notes ?? '';
            $this->isActive = $customer->is_active;
        }
    }

    public function save(): mixed
    {
        $this->authorizeSalamiMasterData();
        $validated = $this->validate();
        $attributes = [
            'name' => $validated['name'],
            'contact_person' => $this->nullableText($validated['contactPerson']),
            'phone' => $this->nullableText($validated['phone']),
            'area' => $this->nullableText($validated['area']),
            'address' => $this->nullableText($validated['address']),
            'notes' => $this->nullableText($validated['notes']),
            'is_active' => $validated['isActive'],
        ];

        if ($this->customer instanceof SalamiCustomer) {
            $this->customer->update([...$attributes, 'updated_by' => auth()->id()]);
            session()->flash('status', 'تم تحديث بيانات المحل.');
        } else {
            $this->customer = SalamiCustomer::query()->create([...$attributes, 'created_by' => auth()->id()]);
            session()->flash('status', 'تم إنشاء المحل.');
        }

        return $this->redirectRoute('salami.customers.show', ['customer' => $this->customer], navigate: true);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contactPerson' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'area' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
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
        return view('livewire.salami.customers.form');
    }
}

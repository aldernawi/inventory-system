<?php

namespace App\Livewire\Salami\DeliveryAgents;

use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiDeliveryAgent;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesSalamiAccess;

    public ?SalamiDeliveryAgent $deliveryAgent = null;

    public string $name = '';

    public string $phone = '';

    public string $notes = '';

    public bool $isActive = true;

    public function mount(?SalamiDeliveryAgent $deliveryAgent = null): void
    {
        $this->authorizeSalamiMasterData();
        $this->deliveryAgent = $deliveryAgent;

        if ($deliveryAgent instanceof SalamiDeliveryAgent) {
            $this->name = $deliveryAgent->name;
            $this->phone = $deliveryAgent->phone ?? '';
            $this->notes = $deliveryAgent->notes ?? '';
            $this->isActive = $deliveryAgent->is_active;
        }
    }

    public function save(): mixed
    {
        $this->authorizeSalamiMasterData();
        $validated = $this->validate();
        $attributes = [
            'name' => $validated['name'],
            'phone' => $this->nullableText($validated['phone']),
            'notes' => $this->nullableText($validated['notes']),
            'is_active' => $validated['isActive'],
        ];

        if ($this->deliveryAgent instanceof SalamiDeliveryAgent) {
            $this->deliveryAgent->update([...$attributes, 'updated_by' => auth()->id()]);
            session()->flash('status', 'تم تحديث بيانات المندوب.');
        } else {
            $this->deliveryAgent = SalamiDeliveryAgent::query()->create([...$attributes, 'created_by' => auth()->id()]);
            session()->flash('status', 'تمت إضافة المندوب.');
        }

        return $this->redirectRoute('salami.delivery-agents.index', navigate: true);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
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
        return view('livewire.salami.delivery-agents.form');
    }
}

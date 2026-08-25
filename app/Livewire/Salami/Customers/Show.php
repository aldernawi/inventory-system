<?php

namespace App\Livewire\Salami\Customers;

use App\Models\SalamiCustomer;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public SalamiCustomer $customer;

    public function mount(SalamiCustomer $customer): void
    {
        $this->customer = $customer;
    }

    public function render(): View
    {
        return view('livewire.salami.customers.show', [
            'customer' => $this->customer->fresh('deliveryAgent'),
        ]);
    }
}

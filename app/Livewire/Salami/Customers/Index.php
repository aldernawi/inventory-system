<?php

namespace App\Livewire\Salami\Customers;

use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiCustomer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesSalamiAccess, WithPagination;

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $customerId): void
    {
        $this->authorizeSalamiMasterData();
        $customer = SalamiCustomer::query()->findOrFail($customerId);

        $customer->update([
            'is_active' => ! $customer->is_active,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('status', 'تم تحديث حالة المحل.');
    }

    public function render(): View
    {
        $customers = SalamiCustomer::query()
            ->with('deliveryAgent')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('contact_person', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%")
                        ->orWhere('area', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.salami.customers.index', compact('customers'));
    }
}

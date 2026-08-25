<?php

namespace App\Livewire\Salami\DeliveryAgents;

use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiDeliveryAgent;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesSalamiAccess, WithPagination;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorizeSalamiMasterData();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $deliveryAgentId): void
    {
        $this->authorizeSalamiMasterData();
        $deliveryAgent = SalamiDeliveryAgent::query()->findOrFail($deliveryAgentId);
        $deliveryAgent->update(['is_active' => ! $deliveryAgent->is_active, 'updated_by' => auth()->id()]);
        session()->flash('status', 'تم تحديث حالة المندوب.');
    }

    public function render(): View
    {
        $deliveryAgents = SalamiDeliveryAgent::query()
            ->withCount(['customers', 'invoices'])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.salami.delivery-agents.index', compact('deliveryAgents'));
    }
}

<?php

namespace App\Livewire\Salami\Invoices;

use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiCustomer;
use App\Models\SalamiDeliveryAgent;
use App\Models\SalamiInvoice;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesSalamiAccess;
    use WithPagination;

    #[Url]
    public string $customerId = '';

    #[Url]
    public string $deliveryAgentId = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $paymentStatus = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->authorizeSalamiInvoices();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'paymentStatus', 'customerId', 'deliveryAgentId', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $invoices = SalamiInvoice::query()
            ->with(['customer', 'deliveryAgent', 'createdBy'])
            ->when($this->search !== '', fn ($query) => $query->where('invoice_number', 'like', "%{$this->search}%"))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->paymentStatus !== '', fn ($query) => $query->where('payment_status', $this->paymentStatus))
            ->when($this->customerId !== '', fn ($query) => $query->where('customer_id', $this->customerId))
            ->when($this->deliveryAgentId !== '', fn ($query) => $query->where('delivery_agent_id', $this->deliveryAgentId))
            ->when($this->from !== '', fn ($query) => $query->whereDate('invoice_date', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('invoice_date', '<=', $this->to))
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(15);

        return view('livewire.salami.invoices.index', [
            'customers' => SalamiCustomer::query()->orderBy('name')->get(['id', 'name']),
            'deliveryAgents' => SalamiDeliveryAgent::query()->orderBy('name')->get(['id', 'name']),
            'invoices' => $invoices,
        ]);
    }
}

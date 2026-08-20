<?php

namespace App\Livewire\Flowers\Invoices;

use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerInvoice;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesFlowerAccess, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $paymentStatus = '';

    #[Url]
    public string $recipientName = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->authorizeFlowerInvoices();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $invoices = FlowerInvoice::query()->with('createdBy')->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('invoice_number', 'like', "%{$this->search}%")->orWhere('recipient_name', 'like', "%{$this->search}%")))->when($this->recipientName !== '', fn ($q) => $q->where('recipient_name', 'like', "%{$this->recipientName}%"))->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->when($this->paymentStatus !== '', fn ($q) => $q->where('payment_status', $this->paymentStatus))->when($this->from !== '', fn ($q) => $q->whereDate('invoice_date', '>=', $this->from))->when($this->to !== '', fn ($q) => $q->whereDate('invoice_date', '<=', $this->to))->latest('invoice_date')->latest('id')->paginate(15);

        return view('livewire.flowers.invoices.index', compact('invoices'));
    }
}

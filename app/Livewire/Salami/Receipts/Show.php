<?php

namespace App\Livewire\Salami\Receipts;

use App\Enums\ReceiptStatus;
use App\Exceptions\Salami\ReceivingException;
use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiReceipt;
use App\Services\Salami\ReceivingService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    use AuthorizesSalamiAccess;

    public SalamiReceipt $receipt;

    public function mount(SalamiReceipt $receipt): void
    {
        $this->receipt = $receipt;
    }

    public function confirm(ReceivingService $receivingService): mixed
    {
        $this->authorizeSalamiReceipts();

        try {
            $this->receipt = $receivingService->confirm($this->receipt, auth()->user());
        } catch (ReceivingException $exception) {
            $this->addError('receipt', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تم اعتماد الإيصال.');

        return $this->redirectRoute('salami.receipts.show', ['receipt' => $this->receipt], navigate: true);
    }

    public function deleteDraft(ReceivingService $receivingService): mixed
    {
        $this->authorizeSalamiReceipts();

        try {
            $receivingService->deleteDraft($this->receipt);
        } catch (ReceivingException $exception) {
            $this->addError('receipt', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تم حذف المسودة. لم يتغير المخزون.');

        return $this->redirectRoute('salami.receipts.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.salami.receipts.show', [
            'receipt' => $this->receipt->fresh([
                'supplier',
                'items.product',
                'items.stockMovement',
                'createdBy',
                'confirmedBy',
            ]),
            'isDraft' => $this->receipt->fresh()->status === ReceiptStatus::Draft,
        ]);
    }
}

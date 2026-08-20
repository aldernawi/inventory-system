<?php

namespace App\Livewire\Flowers\Receipts;

use App\Enums\ReceiptStatus;
use App\Exceptions\Flowers\ReceivingException;
use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerReceipt;
use App\Services\Flowers\ReceivingService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    use AuthorizesFlowerAccess;

    public FlowerReceipt $receipt;

    public function mount(FlowerReceipt $receipt): void
    {
        $this->authorizeFlowerInventory();
        $this->receipt = $receipt;
    }

    public function confirm(ReceivingService $receivingService): mixed
    {
        $this->authorizeFlowerReceipts();

        try {
            $this->receipt = $receivingService->confirm($this->receipt, auth()->user());
        } catch (ReceivingException $exception) {
            $this->addError('receipt', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تم اعتماد الإيصال.');

        return $this->redirectRoute('flowers.receipts.show', ['receipt' => $this->receipt], navigate: true);
    }

    public function deleteDraft(ReceivingService $receivingService): mixed
    {
        $this->authorizeFlowerReceipts();

        try {
            $receivingService->deleteDraft($this->receipt);
        } catch (ReceivingException $exception) {
            $this->addError('receipt', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تم حذف المسودة. لم يتغير المخزون.');

        return $this->redirectRoute('flowers.receipts.index', navigate: true);
    }

    public function render(): View
    {
        $receipt = $this->receipt->fresh(['supplier', 'items.product', 'items.stockMovement', 'createdBy', 'confirmedBy']);

        return view('livewire.flowers.receipts.show', ['receipt' => $receipt, 'isDraft' => $receipt->status === ReceiptStatus::Draft]);
    }
}

<?php

namespace App\Livewire\Flowers\Invoices;

use App\Enums\InvoiceStatus;
use App\Exceptions\Flowers\InvoiceException;
use App\Exceptions\Inventory\InvoicePaymentException;
use App\Livewire\Concerns\AuthorizesFlowerAccess;
use App\Models\FlowerInvoice;
use App\Services\Flowers\InvoiceService;
use App\Services\Inventory\InvoicePaymentService;
use App\Support\Quantity;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Show extends Component
{
    use AuthorizesFlowerAccess;

    public FlowerInvoice $invoice;

    public string $cancellationReason = '';

    public string $paymentAmount = '';

    public string $paymentNotes = '';

    public function mount(FlowerInvoice $invoice): void
    {
        $this->authorizeFlowerInvoices();
        $this->invoice = $invoice;
    }

    public function confirm(InvoiceService $service): mixed
    {
        $this->authorizeFlowerInvoices();
        try {
            $this->invoice = $service->confirm($this->invoice, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return null;
        } catch (InvoiceException $e) {
            $this->addError('invoice', $e->getMessage());

            return null;
        }session()->flash('status', 'تم اعتماد فاتورة الورد وخصم الكميات من المخزون.');

        return $this->redirectRoute('flowers.invoices.show', ['invoice' => $this->invoice], navigate: true);
    }

    public function cancel(InvoiceService $service): mixed
    {
        $this->authorizeFlowerInvoiceCancellation();
        try {
            $this->invoice = $service->cancel($this->invoice, auth()->user(), $this->cancellationReason);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return null;
        } catch (InvoiceException $e) {
            $this->addError('invoice', $e->getMessage());

            return null;
        }session()->flash('status', 'تم إلغاء فاتورة الورد وعكس حركات البيع.');

        return $this->redirectRoute('flowers.invoices.show', ['invoice' => $this->invoice], navigate: true);
    }

    public function deleteDraft(InvoiceService $service): mixed
    {
        $this->authorizeFlowerInvoices();
        try {
            $service->deleteDraft($this->invoice);
        } catch (InvoiceException $e) {
            $this->addError('invoice', $e->getMessage());

            return null;
        }session()->flash('status', 'تم حذف مسودة الفاتورة. لم يتغير المخزون.');

        return $this->redirectRoute('flowers.invoices.index', navigate: true);
    }

    public function fillRemaining(): void
    {
        $this->paymentAmount = $this->invoice->fresh()->remaining_amount;
    }

    public function settlePayment(InvoicePaymentService $paymentService): mixed
    {
        $this->authorizeFlowerInvoices();

        try {
            $paymentService->record(
                $this->invoice,
                $this->paymentAmount,
                auth()->user(),
                $this->paymentNotes,
            );
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return null;
        } catch (InvoicePaymentException $exception) {
            $this->addError('payment_amount', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تم تسجيل الدفعة وتحديث المبلغ المتبقي.');

        return $this->redirectRoute('flowers.invoices.show', ['invoice' => $this->invoice], navigate: true);
    }

    public function render(): View
    {
        $invoice = $this->invoice->fresh(['items.product', 'items.stockMovement', 'createdBy', 'confirmedBy', 'cancelledBy', 'payments.createdBy']);

        return view('livewire.flowers.invoices.show', ['invoice' => $invoice, 'isDraft' => $invoice->status === InvoiceStatus::Draft, 'isConfirmed' => $invoice->status === InvoiceStatus::Confirmed, 'hasRemaining' => Quantity::from($invoice->remaining_amount)->isPositive()]);
    }
}

<?php

namespace App\Livewire\Salami\Invoices;

use App\Enums\InvoiceStatus;
use App\Exceptions\Inventory\InvoicePaymentException;
use App\Exceptions\Salami\InvoiceException;
use App\Livewire\Concerns\AuthorizesSalamiAccess;
use App\Models\SalamiInvoice;
use App\Services\Inventory\InvoicePaymentService;
use App\Services\Salami\InvoiceService;
use App\Support\Quantity;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Show extends Component
{
    use AuthorizesSalamiAccess;

    public string $cancellationReason = '';

    public string $paymentAmount = '';

    public string $paymentNotes = '';

    public SalamiInvoice $invoice;

    public function mount(SalamiInvoice $invoice): void
    {
        $this->authorizeSalamiInvoices();
        $this->invoice = $invoice;
    }

    public function confirm(InvoiceService $invoiceService): mixed
    {
        $this->authorizeSalamiInvoices();

        try {
            $this->invoice = $invoiceService->confirm($this->invoice, auth()->user());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return null;
        } catch (InvoiceException $exception) {
            $this->addError('invoice', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تم اعتماد الفاتورة وخصم الكميات من المخزون.');

        return $this->redirectRoute('salami.invoices.show', ['invoice' => $this->invoice], navigate: true);
    }

    public function cancel(InvoiceService $invoiceService): mixed
    {
        $this->authorizeSalamiInvoiceCancellation();

        try {
            $this->invoice = $invoiceService->cancel($this->invoice, auth()->user(), $this->cancellationReason);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return null;
        } catch (InvoiceException $exception) {
            $this->addError('invoice', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تم إلغاء الفاتورة وعكس حركات البيع.');

        return $this->redirectRoute('salami.invoices.show', ['invoice' => $this->invoice], navigate: true);
    }

    public function deleteDraft(InvoiceService $invoiceService): mixed
    {
        $this->authorizeSalamiInvoices();

        try {
            $invoiceService->deleteDraft($this->invoice);
        } catch (InvoiceException $exception) {
            $this->addError('invoice', $exception->getMessage());

            return null;
        }

        session()->flash('status', 'تم حذف مسودة الفاتورة. لم يتغير المخزون.');

        return $this->redirectRoute('salami.invoices.index', navigate: true);
    }

    public function fillRemaining(): void
    {
        $this->paymentAmount = $this->invoice->fresh()->remaining_amount;
    }

    public function settlePayment(InvoicePaymentService $paymentService): mixed
    {
        $this->authorizeSalamiInvoices();

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

        return $this->redirectRoute('salami.invoices.show', ['invoice' => $this->invoice], navigate: true);
    }

    public function render(): View
    {
        $invoice = $this->invoice->fresh([
            'customer',
            'deliveryAgent',
            'items.product',
            'items.stockMovement',
            'createdBy',
            'confirmedBy',
            'cancelledBy',
            'payments.createdBy',
        ]);

        return view('livewire.salami.invoices.show', [
            'invoice' => $invoice,
            'isDraft' => $invoice->status === InvoiceStatus::Draft,
            'isConfirmed' => $invoice->status === InvoiceStatus::Confirmed,
            'hasRemaining' => Quantity::from($invoice->remaining_amount)->isPositive(),
        ]);
    }
}

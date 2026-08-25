<?php

namespace App\Services\Inventory;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\Inventory\InvalidStockQuantityException;
use App\Exceptions\Inventory\InvoicePaymentException;
use App\Models\FlowerInvoice;
use App\Models\InvoicePayment;
use App\Models\SalamiInvoice;
use App\Models\User;
use App\Support\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoicePaymentService
{
    /**
     * Record a payment against a confirmed Salami or Flower invoice.
     *
     * The invoice row is locked before the remaining balance is read so two
     * employees cannot both collect the same outstanding amount.
     */
    public function record(
        SalamiInvoice|FlowerInvoice $invoice,
        Quantity|int|string $amount,
        User $createdBy,
        ?string $notes = null,
        ?Carbon $paidAt = null,
    ): InvoicePayment {
        try {
            $amount = Quantity::from($amount);
        } catch (InvalidStockQuantityException $exception) {
            throw ValidationException::withMessages(['payment_amount' => 'أدخل مبلغًا صحيحًا بثلاث خانات عشرية كحد أقصى.']);
        }

        if (! $amount->isPositive()) {
            throw ValidationException::withMessages(['payment_amount' => 'مبلغ الدفعة يجب أن يكون أكبر من صفر.']);
        }

        $notes = $this->nullableText($notes);

        return DB::transaction(function () use ($invoice, $amount, $createdBy, $notes, $paidAt): InvoicePayment {
            /** @var SalamiInvoice|FlowerInvoice|null $locked */
            $locked = $invoice::query()->lockForUpdate()->find($invoice->getKey());

            if (! $locked instanceof SalamiInvoice && ! $locked instanceof FlowerInvoice) {
                throw new InvoicePaymentException('الفاتورة لم تعد موجودة.');
            }

            if ($locked->status !== InvoiceStatus::Confirmed) {
                throw new InvoicePaymentException('يمكن تسجيل دفعة على فاتورة معتمدة فقط.');
            }

            $remaining = Quantity::from($locked->remaining_amount);
            if ($remaining->isZero()) {
                throw new InvoicePaymentException('الفاتورة مسددة بالكامل ولا يوجد مبلغ متبقٍ.');
            }

            if ($amount->isGreaterThan($remaining)) {
                throw ValidationException::withMessages([
                    'payment_amount' => "الدفعة لا يمكن أن تتجاوز المتبقي ({$remaining->toString()}).",
                ]);
            }

            $paid = Quantity::from($locked->paid_amount)->plus($amount);
            $newRemaining = $remaining->minus($amount);
            $paymentStatus = $newRemaining->isZero() ? PaymentStatus::Paid : PaymentStatus::Partial;

            $payment = $locked->payments()->create([
                'amount' => $amount->toString(),
                'paid_at' => $paidAt ?? now(),
                'notes' => $notes,
                'created_by' => $createdBy->getKey(),
            ]);

            $locked::allowPaymentMutation(fn () => $locked->update([
                'paid_amount' => $paid->toString(),
                'remaining_amount' => $newRemaining->toString(),
                'payment_status' => $paymentStatus,
                'updated_by' => $createdBy->getKey(),
            ]));

            return $payment->fresh(['invoiceable', 'createdBy']);
        });
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

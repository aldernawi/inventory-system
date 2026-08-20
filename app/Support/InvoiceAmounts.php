<?php

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Exceptions\Inventory\InvalidStockQuantityException;
use Illuminate\Validation\ValidationException;

final class InvoiceAmounts
{
    /**
     * @param  list<array{quantity: Quantity|int|string, unit_price: Quantity|int|string}>  $items
     * @return array{items: list<array{quantity: string, unit_price: string, line_total: string}>, subtotal_amount: string, discount_amount: string, total_amount: string, paid_amount: string, remaining_amount: string, payment_status: PaymentStatus}
     */
    public static function calculate(array $items, Quantity|int|string $discount, Quantity|int|string $paid, PaymentType|string $paymentType): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'أضف صنفًا واحدًا على الأقل إلى الفاتورة.']);
        }

        $subtotal = Quantity::zero();
        $calculatedItems = [];

        foreach ($items as $index => $item) {
            $quantity = Quantity::from($item['quantity']);
            $unitPrice = Quantity::from($item['unit_price']);

            if (! $quantity->isPositive()) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => 'كمية البيع يجب أن تكون أكبر من صفر.']);
            }

            if ($unitPrice->isNegative()) {
                throw ValidationException::withMessages(["items.{$index}.unit_price" => 'سعر الوحدة يجب أن يكون صفرًا أو أكبر.']);
            }

            $lineTotal = $quantity->multipliedBy($unitPrice);
            $subtotal = $subtotal->plus($lineTotal);
            $calculatedItems[] = [
                'quantity' => $quantity->toString(),
                'unit_price' => $unitPrice->toString(),
                'line_total' => $lineTotal->toString(),
            ];
        }

        $discount = Quantity::from($discount);
        $paid = Quantity::from($paid);

        if ($discount->isNegative() || $paid->isNegative()) {
            throw new InvalidStockQuantityException('الخصم والمبلغ المدفوع يجب أن يكونا صفرًا أو أكبر.');
        }

        if ($discount->isGreaterThan($subtotal)) {
            throw ValidationException::withMessages(['discount_amount' => 'الخصم لا يمكن أن يتجاوز المجموع الفرعي.']);
        }

        $total = $subtotal->minus($discount);

        if ($paid->isGreaterThan($total)) {
            throw ValidationException::withMessages(['paid_amount' => 'المبلغ المدفوع لا يمكن أن يتجاوز إجمالي الفاتورة.']);
        }

        try {
            $paymentType = is_string($paymentType) ? PaymentType::from($paymentType) : $paymentType;
        } catch (\ValueError) {
            throw ValidationException::withMessages(['payment_type' => 'اختر نوع دفع صالحًا.']);
        }
        $paymentStatus = $paid->isEqualTo($total)
            ? PaymentStatus::Paid
            : ($paid->isZero() ? PaymentStatus::Unpaid : PaymentStatus::Partial);

        if (($paymentType === PaymentType::Cash && $paymentStatus !== PaymentStatus::Paid)
            || ($paymentType === PaymentType::Credit && $paymentStatus !== PaymentStatus::Unpaid)
            || ($paymentType === PaymentType::Partial && $paymentStatus !== PaymentStatus::Partial)) {
            throw ValidationException::withMessages(['payment_type' => 'نوع الدفع يجب أن يطابق المبلغ المدفوع وحالة السداد.']);
        }

        return [
            'items' => $calculatedItems,
            'subtotal_amount' => $subtotal->toString(),
            'discount_amount' => $discount->toString(),
            'total_amount' => $total->toString(),
            'paid_amount' => $paid->toString(),
            'remaining_amount' => $total->minus($paid)->toString(),
            'payment_status' => $paymentStatus,
        ];
    }
}

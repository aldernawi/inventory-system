<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Closure;
use Database\Factories\SalamiInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['invoice_number', 'customer_id', 'invoice_date', 'payment_type', 'payment_status', 'subtotal_amount', 'discount_amount', 'total_amount', 'paid_amount', 'remaining_amount', 'status', 'notes', 'created_by', 'updated_by', 'confirmed_by', 'confirmed_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason'])]
class SalamiInvoice extends Model
{
    /** @use HasFactory<SalamiInvoiceFactory> */
    use HasFactory;

    private static bool $allowsConfirmedMutation = false;

    protected static function booted(): void
    {
        static::updating(function (self $invoice): void {
            if ($invoice->getRawOriginal('status') === InvoiceStatus::Draft->value) {
                return;
            }

            if (self::$allowsConfirmedMutation
                && $invoice->getRawOriginal('status') === InvoiceStatus::Confirmed->value
                && $invoice->status === InvoiceStatus::Cancelled) {
                return;
            }

            throw new LogicException('Confirmed invoices are immutable except through the cancellation service.');
        });

        static::deleting(function (self $invoice): void {
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw new LogicException('Confirmed invoices cannot be deleted.');
            }
        });
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function allowConfirmedMutation(Closure $callback): mixed
    {
        self::$allowsConfirmedMutation = true;

        try {
            return $callback();
        } finally {
            self::$allowsConfirmedMutation = false;
        }
    }

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'payment_type' => PaymentType::class,
            'payment_status' => PaymentStatus::class,
            'subtotal_amount' => 'decimal:3',
            'discount_amount' => 'decimal:3',
            'total_amount' => 'decimal:3',
            'paid_amount' => 'decimal:3',
            'remaining_amount' => 'decimal:3',
            'status' => InvoiceStatus::class,
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(SalamiCustomer::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalamiInvoiceItem::class, 'invoice_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}

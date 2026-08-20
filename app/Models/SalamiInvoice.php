<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Database\Factories\SalamiInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['invoice_number', 'customer_id', 'invoice_date', 'payment_type', 'payment_status', 'subtotal_amount', 'discount_amount', 'total_amount', 'paid_amount', 'remaining_amount', 'status', 'notes', 'created_by', 'updated_by', 'confirmed_by', 'confirmed_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason'])]
class SalamiInvoice extends Model
{
    /** @use HasFactory<SalamiInvoiceFactory> */
    use HasFactory;

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

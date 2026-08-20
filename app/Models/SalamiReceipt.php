<?php

namespace App\Models;

use App\Enums\ReceiptStatus;
use Database\Factories\SalamiReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['receipt_number', 'supplier_id', 'supplier_invoice_number', 'receipt_date', 'status', 'notes', 'created_by', 'updated_by', 'confirmed_by', 'confirmed_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason'])]
class SalamiReceipt extends Model
{
    /** @use HasFactory<SalamiReceiptFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'status' => ReceiptStatus::class,
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalamiReceiptItem::class, 'receipt_id');
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

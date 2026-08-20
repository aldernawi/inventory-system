<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\SalamiInvoiceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use LogicException;

#[Fillable(['invoice_id', 'product_id', 'product_name', 'unit', 'quantity', 'unit_price', 'line_total'])]
class SalamiInvoiceItem extends Model
{
    /** @use HasFactory<SalamiInvoiceItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $item): void {
            $item->ensureDraftInvoice();
        });

        static::deleting(function (self $item): void {
            $item->ensureDraftInvoice();
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:3',
            'line_total' => 'decimal:3',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalamiInvoice::class, 'invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SalamiProduct::class, 'product_id');
    }

    public function stockMovement(): MorphOne
    {
        return $this->morphOne(StockMovement::class, 'reference');
    }

    private function ensureDraftInvoice(): void
    {
        if ($this->invoice()->first()?->status !== InvoiceStatus::Draft) {
            throw new LogicException('Items on confirmed invoices are immutable.');
        }
    }
}

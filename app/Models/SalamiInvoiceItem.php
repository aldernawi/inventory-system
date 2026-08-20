<?php

namespace App\Models;

use Database\Factories\SalamiInvoiceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['invoice_id', 'product_id', 'product_name', 'unit', 'quantity', 'unit_price', 'line_total'])]
class SalamiInvoiceItem extends Model
{
    /** @use HasFactory<SalamiInvoiceItemFactory> */
    use HasFactory;

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
}

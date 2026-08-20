<?php

namespace App\Models;

use App\Enums\ReceiptStatus;
use Database\Factories\FlowerReceiptItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use LogicException;

#[Fillable(['receipt_id', 'flower_product_id', 'product_name', 'color', 'unit', 'expected_quantity', 'received_quantity', 'damaged_quantity', 'shortage_quantity', 'surplus_quantity', 'accepted_quantity', 'purchase_price', 'balance_before', 'balance_after', 'notes'])]
class FlowerReceiptItem extends Model
{
    /** @use HasFactory<FlowerReceiptItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->ensureDraftReceipt();
        });

        static::updating(function (self $item): void {
            $item->ensureDraftReceipt();
        });

        static::deleting(function (self $item): void {
            $item->ensureDraftReceipt();
        });
    }

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'damaged_quantity' => 'decimal:3',
            'shortage_quantity' => 'decimal:3',
            'surplus_quantity' => 'decimal:3',
            'accepted_quantity' => 'decimal:3',
            'purchase_price' => 'decimal:3',
            'balance_before' => 'decimal:3',
            'balance_after' => 'decimal:3',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(FlowerReceipt::class, 'receipt_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(FlowerProduct::class, 'flower_product_id');
    }

    public function stockMovement(): MorphOne
    {
        return $this->morphOne(StockMovement::class, 'reference');
    }

    private function ensureDraftReceipt(): void
    {
        if ($this->receipt()->first()?->status !== ReceiptStatus::Draft) {
            throw new LogicException('Items on confirmed flower receipts are immutable.');
        }
    }
}

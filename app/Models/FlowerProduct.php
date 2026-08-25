<?php

namespace App\Models;

use App\Contracts\Stockable;
use App\Models\Concerns\HasStockableRecords;
use Database\Factories\FlowerProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'company_name', 'code', 'color', 'grade', 'unit', 'purchase_price', 'sale_price', 'minimum_quantity', 'notes', 'is_active', 'created_by', 'updated_by'])]
class FlowerProduct extends Model implements Stockable
{
    /** @use HasFactory<FlowerProductFactory> */
    use HasFactory, HasStockableRecords;

    protected function casts(): array
    {
        return [
            'current_quantity' => 'decimal:3',
            'purchase_price' => 'decimal:3',
            'sale_price' => 'decimal:3',
            'minimum_quantity' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(FlowerReceiptItem::class, 'flower_product_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(FlowerInvoiceItem::class, 'flower_product_id');
    }

    public function exits(): HasMany
    {
        return $this->hasMany(FlowerExit::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

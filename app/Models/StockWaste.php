<?php

namespace App\Models;

use App\Enums\InventoryRecordStatus;
use Database\Factories\StockWasteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

#[Fillable(['stockable_type', 'stockable_id', 'quantity', 'reason', 'waste_date', 'notes', 'status', 'created_by', 'confirmed_by', 'confirmed_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason'])]
class StockWaste extends Model
{
    /** @use HasFactory<StockWasteFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Confirmed waste records are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Confirmed waste records cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'waste_date' => 'date',
            'status' => InventoryRecordStatus::class,
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function stockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function stockMovement(): MorphOne
    {
        return $this->morphOne(StockMovement::class, 'reference');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}

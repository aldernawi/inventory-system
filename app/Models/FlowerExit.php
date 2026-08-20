<?php

namespace App\Models;

use App\Enums\FlowerExitType;
use App\Enums\InventoryRecordStatus;
use Database\Factories\FlowerExitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['flower_product_id', 'quantity', 'exit_date', 'exit_type', 'recipient_name', 'notes', 'status', 'created_by', 'confirmed_by', 'confirmed_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason'])]
class FlowerExit extends Model
{
    /** @use HasFactory<FlowerExitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'exit_date' => 'date',
            'exit_type' => FlowerExitType::class,
            'status' => InventoryRecordStatus::class,
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(FlowerProduct::class, 'flower_product_id');
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

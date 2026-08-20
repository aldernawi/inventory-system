<?php

namespace App\Models;

use Database\Factories\StockOpeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['stockable_type', 'stockable_id', 'quantity', 'opening_date', 'notes', 'created_by'])]
class StockOpening extends Model
{
    /** @use HasFactory<StockOpeningFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'opening_date' => 'date',
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
}

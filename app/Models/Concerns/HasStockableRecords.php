<?php

namespace App\Models\Concerns;

use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockOpening;
use App\Models\StockWaste;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasStockableRecords
{
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'stockable');
    }

    public function stockWastes(): MorphMany
    {
        return $this->morphMany(StockWaste::class, 'stockable');
    }

    public function stockAdjustments(): MorphMany
    {
        return $this->morphMany(StockAdjustment::class, 'stockable');
    }

    public function stockOpenings(): MorphMany
    {
        return $this->morphMany(StockOpening::class, 'stockable');
    }
}

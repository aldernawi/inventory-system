<?php

namespace App\Services\Inventory;

use App\Models\StockMovement;

class StockMovementService
{
    /**
     * Persists a new immutable movement. The caller must already be in the
     * same transaction as the corresponding stock projection update.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes): StockMovement
    {
        return StockMovement::query()->create($attributes);
    }
}

<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Stockable
{
    public function stockMovements(): MorphMany;

    public function stockWastes(): MorphMany;

    public function stockAdjustments(): MorphMany;

    public function stockOpenings(): MorphMany;
}

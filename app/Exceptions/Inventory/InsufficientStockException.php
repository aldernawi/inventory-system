<?php

namespace App\Exceptions\Inventory;

class InsufficientStockException extends StockMutationException
{
    public function __construct(string $available, string $requested)
    {
        parent::__construct("Insufficient stock. Available: {$available}; requested: {$requested}.");
    }
}

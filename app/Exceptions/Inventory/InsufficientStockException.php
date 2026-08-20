<?php

namespace App\Exceptions\Inventory;

class InsufficientStockException extends StockMutationException
{
    public function __construct(
        public readonly string $available,
        public readonly string $requested,
    ) {
        parent::__construct("Insufficient stock. Available: {$available}; requested: {$requested}.");
    }
}

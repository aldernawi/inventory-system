<?php

namespace App\Support;

use App\Exceptions\Inventory\InvalidStockQuantityException;

final class ReceivingQuantities
{
    /**
     * Calculate receiving values using fixed-scale decimal arithmetic.
     *
     * @return array{expected_quantity: string, received_quantity: string, damaged_quantity: string, shortage_quantity: string, surplus_quantity: string, accepted_quantity: string}
     */
    public static function calculate(Quantity|int|string $expected, Quantity|int|string $received, Quantity|int|string $damaged): array
    {
        $expected = Quantity::from($expected);
        $received = Quantity::from($received);
        $damaged = Quantity::from($damaged);

        if ($expected->isNegative() || $received->isNegative() || $damaged->isNegative()) {
            throw new InvalidStockQuantityException('Expected, received, and damaged quantities must be zero or greater.');
        }

        if ($damaged->isGreaterThan($received)) {
            throw new InvalidStockQuantityException('Damaged quantity cannot exceed received quantity.');
        }

        $zero = Quantity::zero();

        return [
            'expected_quantity' => $expected->toString(),
            'received_quantity' => $received->toString(),
            'damaged_quantity' => $damaged->toString(),
            'shortage_quantity' => $expected->isGreaterThan($received) ? $expected->minus($received)->toString() : $zero->toString(),
            'surplus_quantity' => $received->isGreaterThan($expected) ? $received->minus($expected)->toString() : $zero->toString(),
            'accepted_quantity' => $received->minus($damaged)->toString(),
        ];
    }
}

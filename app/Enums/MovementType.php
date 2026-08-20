<?php

namespace App\Enums;

enum MovementType: string
{
    case Opening = 'opening';
    case Receipt = 'receipt';
    case Sale = 'sale';
    case ManualExit = 'manual_exit';
    case Waste = 'waste';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case Reversal = 'reversal';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}

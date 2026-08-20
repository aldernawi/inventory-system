<?php

namespace App\Enums;

enum InventoryRecordStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}

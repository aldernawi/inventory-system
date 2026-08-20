<?php

namespace App\Enums;

enum FlowerExitType: string
{
    case Gift = 'gift';
    case InternalUse = 'internal_use';
    case Sample = 'sample';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}

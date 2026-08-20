<?php

namespace App\Enums;

enum PaymentType: string
{
    case Cash = 'cash';
    case Credit = 'credit';
    case Partial = 'partial';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}

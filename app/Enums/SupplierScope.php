<?php

namespace App\Enums;

enum SupplierScope: string
{
    case Salami = 'salami';
    case Flower = 'flower';
    case Both = 'both';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $scope): string => $scope->value, self::cases());
    }
}

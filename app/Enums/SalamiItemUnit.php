<?php

namespace App\Enums;

enum SalamiItemUnit: string
{
    case Piece = 'piece';
    case Box = 'box';

    public function label(): string
    {
        return match ($this) {
            self::Piece => 'قطعة',
            self::Box => 'صندوق',
        };
    }

    public static function fromStoredLabel(string $label): self
    {
        return $label === self::Box->label() ? self::Box : self::Piece;
    }
}

<?php

namespace App\Support;

use App\Enums\SalamiItemUnit;
use Illuminate\Validation\ValidationException;

final class SalamiUnitConversion
{
    /**
     * @return array{unit: SalamiItemUnit, factor: Quantity, stock_quantity: Quantity}
     */
    public static function fromInput(string $unit, string $quantity, string|int|null $piecesPerBox, bool $requiresPositive = true): array
    {
        try {
            $selectedUnit = SalamiItemUnit::from($unit);
        } catch (\ValueError) {
            throw ValidationException::withMessages(['unit_type' => 'اختر قطعة أو صندوق.']);
        }

        $enteredQuantity = Quantity::from($quantity);

        if ($requiresPositive && ! $enteredQuantity->isPositive()) {
            throw ValidationException::withMessages(['quantity' => 'الكمية يجب أن تكون أكبر من صفر.']);
        }

        if (! $requiresPositive && $enteredQuantity->isNegative()) {
            throw ValidationException::withMessages(['quantity' => 'الكمية يجب أن تكون صفرًا أو أكبر.']);
        }

        $factor = match ($selectedUnit) {
            SalamiItemUnit::Piece => Quantity::from(1),
            SalamiItemUnit::Box => self::piecesPerBox($piecesPerBox),
        };

        return [
            'unit' => $selectedUnit,
            'factor' => $factor,
            'stock_quantity' => $enteredQuantity->multipliedBy($factor),
        ];
    }

    public static function piecesPerBox(string|int|null $piecesPerBox): Quantity
    {
        $value = trim((string) $piecesPerBox);

        if (! preg_match('/^[1-9]\d*$/', $value)) {
            throw ValidationException::withMessages(['pieces_per_box' => 'أدخل عدد القطع داخل الصندوق كرقم صحيح أكبر من صفر.']);
        }

        return Quantity::from($value);
    }

    public static function description(SalamiItemUnit $unit, Quantity $quantity, Quantity $factor): string
    {
        if ($unit === SalamiItemUnit::Piece) {
            return $quantity->toString().' قطعة';
        }

        return "{$quantity->toString()} صندوق × {$factor->toString()} قطعة = ".$quantity->multipliedBy($factor)->toString().' قطعة';
    }
}

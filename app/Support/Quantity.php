<?php

namespace App\Support;

use App\Exceptions\Inventory\InvalidStockQuantityException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Quantity
{
    private const SCALE = 3;

    private function __construct(private readonly BigDecimal $value) {}

    public static function from(self|int|string $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        try {
            return new self(BigDecimal::of((string) $value)->toScale(self::SCALE, RoundingMode::Unnecessary));
        } catch (\Throwable $exception) {
            throw new InvalidStockQuantityException('Quantities must be valid decimal values with at most three decimal places.', previous: $exception);
        }
    }

    public static function zero(): self
    {
        return new self(BigDecimal::zero()->toScale(self::SCALE));
    }

    public function plus(self $quantity): self
    {
        return new self($this->value->plus($quantity->value)->toScale(self::SCALE, RoundingMode::Unnecessary));
    }

    public function minus(self $quantity): self
    {
        return new self($this->value->minus($quantity->value)->toScale(self::SCALE, RoundingMode::Unnecessary));
    }

    /**
     * Invoice line totals are rounded once, half up, to the database's 3-decimal scale.
     */
    public function multipliedBy(self $quantity): self
    {
        return new self($this->value->multipliedBy($quantity->value)->toScale(self::SCALE, RoundingMode::HalfUp));
    }

    public function negated(): self
    {
        return new self($this->value->negated()->toScale(self::SCALE, RoundingMode::Unnecessary));
    }

    public function absolute(): self
    {
        return $this->isNegative() ? $this->negated() : $this;
    }

    public function isPositive(): bool
    {
        return $this->value->compareTo(BigDecimal::zero()) > 0;
    }

    public function isNegative(): bool
    {
        return $this->value->compareTo(BigDecimal::zero()) < 0;
    }

    public function isZero(): bool
    {
        return $this->value->compareTo(BigDecimal::zero()) === 0;
    }

    public function isGreaterThan(self $quantity): bool
    {
        return $this->value->compareTo($quantity->value) > 0;
    }

    public function isLessThan(self $quantity): bool
    {
        return $this->value->compareTo($quantity->value) < 0;
    }

    public function isEqualTo(self $quantity): bool
    {
        return $this->value->compareTo($quantity->value) === 0;
    }

    public function toString(): string
    {
        return (string) $this->value;
    }
}

<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final readonly class Points implements JsonSerializable
{
    public function __construct(private int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Points cannot be negative');
        }
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function add(Points $other): self
    {
        return new self($this->value + $other->value);
    }

    public function subtract(Points $other): self
    {
        $result = $this->value - $other->value;

        if ($result < 0) {
            throw new InvalidArgumentException('Cannot subtract more points than available');
        }

        return new self($result);
    }

    public function multiply(float $multiplier): self
    {
        if ($multiplier < 0) {
            throw new InvalidArgumentException('Multiplier cannot be negative');
        }

        return new self((int) round($this->value * $multiplier));
    }

    public function divide(float $divisor): self
    {
        if ($divisor <= 0) {
            throw new InvalidArgumentException('Divisor must be positive');
        }

        return new self((int) round($this->value / $divisor));
    }

    public function percentage(float $percentage): self
    {
        if ($percentage < 0 || $percentage > 100) {
            throw new InvalidArgumentException('Percentage must be between 0 and 100');
        }

        return new self((int) round($this->value * ($percentage / 100)));
    }

    public function isGreaterThan(Points $other): bool
    {
        return $this->value > $other->value;
    }

    public function isGreaterThanOrEqual(Points $other): bool
    {
        return $this->value >= $other->value;
    }

    public function isLessThan(Points $other): bool
    {
        return $this->value < $other->value;
    }

    public function isZero(): bool
    {
        return $this->value === 0;
    }

    public function equals(Points $other): bool
    {
        return $this->value === $other->value;
    }

    public function jsonSerialize(): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return number_format($this->value) . ' points';
    }
}

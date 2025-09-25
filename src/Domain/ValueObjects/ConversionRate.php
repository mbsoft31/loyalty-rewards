<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final readonly class ConversionRate implements JsonSerializable
{
    public function __construct(private float $multiplier)
    {
        if ($multiplier <= 0) {
            throw new InvalidArgumentException('Conversion rate multiplier must be positive');
        }
    }

    public static function standard(): self
    {
        return new self(1.0); // 1 cent = 1 point
    }

    public static function fromMultiplier(float $multiplier): self
    {
        return new self($multiplier);
    }

    public static function fromRatio(int $cents, int $points): self
    {
        if ($cents <= 0 || $points <= 0) {
            throw new InvalidArgumentException('Both cents and points must be positive');
        }

        return new self($points / $cents);
    }

    public function multiplier(): float
    {
        return $this->multiplier;
    }

    public function inverse(): self
    {
        return new self(1.0 / $this->multiplier);
    }

    public function equals(ConversionRate $other): bool
    {
        return abs($this->multiplier - $other->multiplier) < 0.001;
    }

    public function jsonSerialize(): float
    {
        return $this->multiplier;
    }

    public function __toString(): string
    {
        return "x{$this->multiplier}";
    }
}

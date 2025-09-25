<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final readonly class Money implements JsonSerializable
{
    public function __construct(
        private int $amount, // Amount in smallest currency unit (cents)
        private Currency $currency
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative');
        }
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public static function fromCents(int $cents, Currency $currency): self
    {
        return new self($cents, $currency);
    }

    public static function fromDollars(float $dollars, Currency $currency): self
    {
        return new self((int) round($dollars * 100), $currency);
    }

    public static function fromString(string $amount, Currency $currency): self
    {
        $cleanAmount = preg_replace('/[^\d.]/', '', $amount);
        $dollars = (float) $cleanAmount;
        return self::fromDollars($dollars, $currency);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function toDollars(): float
    {
        return $this->amount / 100;
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        if ($this->amount < $other->amount) {
            throw new InvalidArgumentException('Cannot subtract more money than available');
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(float $multiplier): self
    {
        if ($multiplier < 0) {
            throw new InvalidArgumentException('Multiplier cannot be negative');
        }

        return new self((int) round($this->amount * $multiplier), $this->currency);
    }

    public function divide(float $divisor): self
    {
        if ($divisor <= 0) {
            throw new InvalidArgumentException('Divisor must be positive');
        }

        return new self((int) round($this->amount / $divisor), $this->currency);
    }

    public function percentage(float $percentage): self
    {
        if ($percentage < 0 || $percentage > 100) {
            throw new InvalidArgumentException('Percentage must be between 0 and 100');
        }

        return new self((int) round($this->amount * ($percentage / 100)), $this->currency);
    }

    public function convertToPoints(ConversionRate $rate): Points
    {
        return Points::fromInt((int) round($this->amount * $rate->multiplier()));
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount &&
            $this->currency->equals($other->currency);
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount > $other->amount;
    }

    public function isLessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount < $other->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    private function assertSameCurrency(Money $other): void
    {
        if (!$this->currency->equals($other->currency)) {
            throw new InvalidArgumentException(
                "Cannot operate on different currencies: {$this->currency} vs {$other->currency}"
            );
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'amount_cents' => $this->amount,
            'amount_dollars' => $this->toDollars(),
            'currency' => $this->currency->code(),
            'formatted' => $this->currency->format($this->toDollars()),
        ];
    }

    public function __toString(): string
    {
        return $this->currency->format($this->toDollars());
    }
}

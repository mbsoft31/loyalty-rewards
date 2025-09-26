<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final class CustomerId implements JsonSerializable
{
    public function __construct(private string $value)
    {
        if (empty(trim($value))) {
            throw new InvalidArgumentException('CustomerId cannot be empty');
        }

        $this->value = trim($value);
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(CustomerId $other): bool
    {
        return $this->value === $other->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

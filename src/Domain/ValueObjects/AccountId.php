<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class AccountId implements JsonSerializable
{
    public function __construct(private UuidInterface $value)
    {
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4());
    }

    public static function fromString(string $id): self
    {
        try {
            return new self(Uuid::fromString($id));
        } catch (\InvalidArgumentException $e) {
            throw new InvalidArgumentException("Invalid AccountId format: {$id}");
        }
    }

    public function toString(): string
    {
        return $this->value->toString();
    }

    public function equals(AccountId $other): bool
    {
        return $this->value->equals($other->value);
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}

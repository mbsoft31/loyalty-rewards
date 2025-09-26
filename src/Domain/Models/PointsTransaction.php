<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\Models;

use LoyaltyRewards\Domain\ValueObjects\{TransactionId, AccountId, Points, TransactionContext};
use LoyaltyRewards\Domain\Enums\TransactionType;
use DateTimeImmutable;
use JsonSerializable;

final readonly class PointsTransaction implements JsonSerializable
{
    public function __construct(
        private TransactionId $id,
        private AccountId $accountId,
        private TransactionType $type,
        private Points $points,
        private TransactionContext $context,
        private DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $processedAt = null
    ) {}

    public static function create(
        AccountId $accountId,
        TransactionType $type,
        Points $points,
        TransactionContext $context
    ): self {
        return new self(
            TransactionId::generate(),
            $accountId,
            $type,
            $points,
            $context,
            new DateTimeImmutable()
        );
    }

    public function getId(): TransactionId
    {
        return $this->id;
    }

    public function getAccountId(): AccountId
    {
        return $this->accountId;
    }

    public function getType(): TransactionType
    {
        return $this->type;
    }

    public function getPoints(): Points
    {
        return $this->points;
    }

    public function getContext(): TransactionContext
    {
        return $this->context;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getProcessedAt(): ?DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function isProcessed(): bool
    {
        return $this->processedAt !== null;
    }

    public function markAsProcessed(): self
    {
        if ($this->isProcessed()) {
            return $this;
        }

        return new self(
            $this->id,
            $this->accountId,
            $this->type,
            $this->points,
            $this->context,
            $this->createdAt,
            new DateTimeImmutable()
        );
    }

    public function isEarning(): bool
    {
        return $this->type->isEarning();
    }

    public function isSpending(): bool
    {
        return $this->type->isSpending();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id->toString(),
            'account_id' => $this->accountId->toString(),
            'type' => $this->type->value,
            'points' => $this->points->value(),
            'context' => $this->context->toArray(),
            'created_at' => $this->createdAt->format('c'),
            'processed_at' => $this->processedAt?->format('c'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\Events;

use DateTimeImmutable;
use LoyaltyRewards\Domain\Models\PointsTransaction;
use LoyaltyRewards\Domain\ValueObjects\AccountId;
use LoyaltyRewards\Domain\ValueObjects\Points;

final readonly class PointsRedeemedEvent
{
    public function __construct(
        public AccountId $accountId,
        public PointsTransaction $transaction,
        public Points $remainingPoints,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable
    ) {}
}

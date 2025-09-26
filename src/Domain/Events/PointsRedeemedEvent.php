<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\Events;

use LoyaltyRewards\Domain\ValueObjects\{AccountId, Points};
use LoyaltyRewards\Domain\Models\PointsTransaction;
use DateTimeImmutable;

final readonly class PointsRedeemedEvent
{
    public function __construct(
        public AccountId $accountId,
        public PointsTransaction $transaction,
        public Points $remainingPoints,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable()
    ) {}
}

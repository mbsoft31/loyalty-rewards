<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\Events;

use DateTimeImmutable;
use LoyaltyRewards\Domain\Models\PointsTransaction;
use LoyaltyRewards\Domain\ValueObjects\{AccountId, Points};

final readonly class PointsEarnedEvent
{
    public function __construct(
        public AccountId $accountId,
        public PointsTransaction $transaction,
        public Points $availablePoints,
        public Points $pendingPoints,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable()
    ) {
    }
}

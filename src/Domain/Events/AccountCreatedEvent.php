<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\Events;

use DateTimeImmutable;
use LoyaltyRewards\Domain\ValueObjects\{AccountId, CustomerId};

final readonly class AccountCreatedEvent
{
    public function __construct(
        public AccountId $accountId,
        public CustomerId $customerId,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable()
    ) {
    }
}

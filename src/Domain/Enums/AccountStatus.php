<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\Enums;

enum AccountStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case CLOSED = 'closed';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canEarnPoints(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canRedeemPoints(): bool
    {
        return $this === self::ACTIVE;
    }
}

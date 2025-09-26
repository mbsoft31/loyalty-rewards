<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\Enums;

enum TransactionType: string
{
    case EARN = 'earn';
    case REDEEM = 'redeem';
    case EXPIRE = 'expire';
    case REFUND = 'refund';
    case ADJUSTMENT = 'adjustment';

    public function isEarning(): bool
    {
        return match ($this) {
            self::EARN, self::REFUND => true,
            default => false,
        };
    }

    public function isSpending(): bool
    {
        return match ($this) {
            self::REDEEM, self::EXPIRE, self::ADJUSTMENT => true,
            default => false,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::EARN => 'Points Earned',
            self::REDEEM => 'Points Redeemed',
            self::EXPIRE => 'Points Expired',
            self::REFUND => 'Points Refunded',
            self::ADJUSTMENT => 'Points Adjustment',
        };
    }
}

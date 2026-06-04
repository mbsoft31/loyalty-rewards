<?php

declare(strict_types=1);

namespace LoyaltyRewards\Tests\Support;

use DateTimeImmutable;
use LoyaltyRewards\Domain\Enums\AccountStatus;
use LoyaltyRewards\Domain\Enums\TransactionType;
use LoyaltyRewards\Domain\Models\LoyaltyAccount;
use LoyaltyRewards\Domain\Models\PointsTransaction;
use LoyaltyRewards\Domain\ValueObjects\AccountId;
use LoyaltyRewards\Domain\ValueObjects\Currency;
use LoyaltyRewards\Domain\ValueObjects\CustomerId;
use LoyaltyRewards\Domain\ValueObjects\Money;
use LoyaltyRewards\Domain\ValueObjects\Points;
use LoyaltyRewards\Domain\ValueObjects\TransactionContext;
use LoyaltyRewards\Domain\ValueObjects\TransactionId;

class Factories
{
    public static function points(int $value = 100): Points
    {
        return Points::fromInt($value);
    }

    public static function money(float $dollars = 100.0, string $currency = 'USD'): Money
    {
        return Money::fromDollars($dollars, new Currency($currency));
    }

    public static function currency(string $code = 'USD'): Currency
    {
        return new Currency($code);
    }

    public static function customerId(?string $id = null): CustomerId
    {
        return CustomerId::fromString($id ?? 'customer_'.uniqid());
    }

    public static function accountId(): AccountId
    {
        return AccountId::generate();
    }

    public static function transactionId(): TransactionId
    {
        return TransactionId::generate();
    }

    public static function transactionContext(array $data = []): TransactionContext
    {
        return TransactionContext::create($data);
    }

    public static function loyaltyAccount(
        ?CustomerId $customerId = null,
        ?Points $availablePoints = null,
        ?Points $pendingPoints = null,
        AccountStatus $status = AccountStatus::ACTIVE
    ): LoyaltyAccount {
        return new LoyaltyAccount(
            self::accountId(),
            $customerId ?? self::customerId(),
            $availablePoints ?? Points::zero(),
            $pendingPoints ?? Points::zero(),
            Points::zero(),
            $status,
            new DateTimeImmutable
        );
    }

    public static function pointsTransaction(
        ?AccountId $accountId = null,
        TransactionType $type = TransactionType::EARN,
        ?Points $points = null,
        ?TransactionContext $context = null
    ): PointsTransaction {
        return PointsTransaction::create(
            $accountId ?? self::accountId(),
            $type,
            $points ?? self::points(),
            $context ?? self::transactionContext()
        );
    }
}

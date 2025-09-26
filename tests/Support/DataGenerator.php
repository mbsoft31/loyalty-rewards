<?php

declare(strict_types=1);

namespace LoyaltyRewards\Tests\Support;

use LoyaltyRewards\Domain\Models\LoyaltyAccount;
use LoyaltyRewards\Domain\ValueObjects\{CustomerId, Points, Money, Currency, TransactionContext};
use LoyaltyRewards\Domain\Enums\{TransactionType, AccountStatus};

class DataGenerator
{
    public static function generateAccounts(int $count): array
    {
        $accounts = [];

        for ($i = 0; $i < $count; $i++) {
            $accounts[] = Factories::loyaltyAccount(
                customerId: CustomerId::fromString("customer_" . str_pad((string)$i, 6, '0', STR_PAD_LEFT)),
                availablePoints: Points::fromInt(rand(0, 10000)),
                pendingPoints: Points::fromInt(rand(0, 1000))
            );
        }

        return $accounts;
    }

    public static function generateTransactionContexts(int $count): array
    {
        $contexts = [];
        $categories = ['electronics', 'groceries', 'gas', 'restaurants', 'travel', 'entertainment'];
        $sources = ['online', 'in_store', 'mobile_app', 'partner'];

        for ($i = 0; $i < $count; $i++) {
            $contexts[] = TransactionContext::create([
                'category' => $categories[array_rand($categories)],
                'source' => $sources[array_rand($sources)],
                'transaction_id' => 'txn_' . uniqid(),
                'timestamp' => (new \DateTimeImmutable())->modify('-' . rand(0, 365) . ' days'),
                'amount' => rand(1000, 50000) / 100, // $10.00 to $500.00
            ]);
        }

        return $contexts;
    }

    public static function generateMoneyAmounts(int $count): array
    {
        $amounts = [];
        $currencies = [Currency::USD(), Currency::EUR(), Currency::GBP()];

        for ($i = 0; $i < $count; $i++) {
            $amounts[] = Money::fromDollars(
                rand(100, 100000) / 100, // $1.00 to $1000.00
                $currencies[array_rand($currencies)]
            );
        }

        return $amounts;
    }

    public static function generateBulkEarningData(int $count): array
    {
        $data = [];

        for ($i = 0; $i < $count; $i++) {
            $data[] = [
                'customer_id' => CustomerId::fromString("bulk_customer_{$i}"),
                'amount' => Money::fromDollars(rand(500, 20000) / 100, Currency::USD()),
                'context' => TransactionContext::create([
                    'category' => ['electronics', 'groceries', 'travel'][rand(0, 2)],
                    'source' => 'bulk_import',
                    'batch_id' => 'batch_' . date('Y-m-d-H-i-s'),
                ]),
            ];
        }

        return $data;
    }

    public static function generateConcurrentOperations(int $operationCount, int $accountCount): array
    {
        $operations = [];
        $operationTypes = ['earn', 'redeem', 'adjust'];

        for ($i = 0; $i < $operationCount; $i++) {
            $operations[] = [
                'type' => $operationTypes[array_rand($operationTypes)],
                'customer_id' => CustomerId::fromString("concurrent_customer_" . rand(1, $accountCount)),
                'points' => Points::fromInt(rand(50, 1000)),
                'amount' => Money::fromDollars(rand(500, 5000) / 100, Currency::USD()),
                'context' => TransactionContext::create([
                    'operation_id' => "op_{$i}",
                    'thread_id' => rand(1, 10),
                ]),
            ];
        }

        return $operations;
    }
}

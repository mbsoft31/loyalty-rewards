<?php

use LoyaltyRewards\Domain\Models\{LoyaltyAccount, PointsTransaction};
use LoyaltyRewards\Domain\ValueObjects\{TransactionId, Points};
use LoyaltyRewards\Domain\Enums\TransactionType;
use LoyaltyRewards\Tests\Support\{DatabaseTestCase, Factories};

describe('Database Transaction Repository Integration', function () {
    beforeEach(function () {
        $this->truncateAllTables();

        // Create test account
        $this->testAccount = Factories::loyaltyAccount();
        $this->accountRepository->save($this->testAccount);
    });

    it('persists and retrieves transactions correctly', function () {
        $transaction = PointsTransaction::create(
            $this->testAccount->getId(),
            TransactionType::EARN,
            Points::fromInt(250),
            Factories::transactionContext(['source' => 'test_purchase'])
        );

        // Save transaction
        $this->transactionRepository->save($transaction);

        // Retrieve transaction
        $retrieved = $this->transactionRepository->findById($transaction->getId());

        expect($retrieved)->not->toBeNull();
        expect($retrieved->getId())->toEqual($transaction->getId());
        expect($retrieved->getAccountId())->toEqual($this->testAccount->getId());
        expect($retrieved->getType())->toBe(TransactionType::EARN);
        expect($retrieved->getPoints())->toBePoints(250);
    });

    it('finds transactions by account efficiently', function () {
        // Create multiple transactions for the account
        $transactions = [];

        for ($i = 0; $i < 50; $i++) {
            $transaction = PointsTransaction::create(
                $this->testAccount->getId(),
                $i % 2 === 0 ? TransactionType::EARN : TransactionType::REDEEM,
                Points::fromInt(rand(50, 500)),
                Factories::transactionContext(['batch' => 'test_batch'])
            );

            $transactions[] = $transaction;
        }

        // Save all transactions
        $this->transactionRepository->saveMany($transactions);

        // Retrieve transactions for account
        $startTime = microtime(true);
        $retrievedTransactions = $this->transactionRepository->findByAccountId($this->testAccount->getId());
        $queryTime = microtime(true) - $startTime;

        expect($retrievedTransactions)->toHaveCount(50);
        expect($queryTime)->toBeLessThan(0.1); // Should be fast with proper indexing
    });

    it('handles bulk transaction saves efficiently', function () {
        $transactions = [];

        // Create 200 transactions
        for ($i = 0; $i < 200; $i++) {
            $transactions[] = PointsTransaction::create(
                $this->testAccount->getId(),
                TransactionType::EARN,
                Points::fromInt($i + 1),
                Factories::transactionContext(['sequence' => $i])
            );
        }

        $startTime = microtime(true);

        // Use batch save
        $this->transactionRepository->saveMany($transactions);

        $saveTime = microtime(true) - $startTime;

        // Performance assertion: Should save 200 transactions quickly
        expect($saveTime)->toBeLessThan(0.5);

        // Verify all were saved
        expect($this->getTableRowCount('points_transactions'))->toBe(200);
    });

    it('filters transactions by type correctly', function () {
        // Create mixed transaction types
        $earnTransaction = PointsTransaction::create(
            $this->testAccount->getId(),
            TransactionType::EARN,
            Points::fromInt(100),
            Factories::transactionContext()
        );

        $redeemTransaction = PointsTransaction::create(
            $this->testAccount->getId(),
            TransactionType::REDEEM,
            Points::fromInt(50),
            Factories::transactionContext()
        );

        $this->transactionRepository->save($earnTransaction);
        $this->transactionRepository->save($redeemTransaction);

        // Filter by type
        $earnTransactions = $this->transactionRepository->findByType(TransactionType::EARN);
        $redeemTransactions = $this->transactionRepository->findByType(TransactionType::REDEEM);

        expect($earnTransactions)->toHaveCount(1);
        expect($redeemTransactions)->toHaveCount(1);
        expect($earnTransactions[0]->getType())->toBe(TransactionType::EARN);
        expect($redeemTransactions[0]->getType())->toBe(TransactionType::REDEEM);
    });

    it('finds transactions by date range accurately', function () {
        $yesterday = new DateTimeImmutable('yesterday');
        $tomorrow = new DateTimeImmutable('tomorrow');

        // Create transaction within range
        $withinRangeTransaction = PointsTransaction::create(
            $this->testAccount->getId(),
            TransactionType::EARN,
            Points::fromInt(100),
            Factories::transactionContext()
        );

        $this->transactionRepository->save($withinRangeTransaction);

        // Find transactions in date range
        $transactions = $this->transactionRepository->findByDateRange($yesterday, $tomorrow);

        expect($transactions)->toHaveCount(1);
        expect($transactions[0]->getId())->toEqual($withinRangeTransaction->getId());
    });

    it('calculates aggregate statistics correctly', function () {
        // Create test data
        $this->transactionRepository->save(PointsTransaction::create(
            $this->testAccount->getId(),
            TransactionType::EARN,
            Points::fromInt(1000),
            Factories::transactionContext()
        ));

        $this->transactionRepository->save(PointsTransaction::create(
            $this->testAccount->getId(),
            TransactionType::REDEEM,
            Points::fromInt(300),
            Factories::transactionContext()
        ));

        // Test aggregate methods
        expect($this->transactionRepository->getTotalTransactions())->toBe(2);
        expect($this->transactionRepository->getTotalPointsEarned())->toBe(1000);
        expect($this->transactionRepository->getTotalPointsRedeemed())->toBe(300);
    });
})->uses(DatabaseTestCase::class);

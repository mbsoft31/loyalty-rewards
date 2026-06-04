<?php

use LoyaltyRewards\Domain\Enums\TransactionType;
use LoyaltyRewards\Tests\Support\DatabaseTestCase;
use LoyaltyRewards\Tests\Support\Factories;

describe('Database Transaction Repository Integration', function () {
    it('persists and retrieves transactions correctly', function () {
        $account = Factories::loyaltyAccount();
        $this->accountRepository->save($account);

        $tx = Factories::pointsTransaction($account->getId());
        $this->transactionRepository->save($tx);

        $found = $this->transactionRepository->findById($tx->getId());
        expect($found)->not->toBeNull();
        expect($found->getAccountId()->toString())->toBe($account->getId()->toString());
    });

    it('handles bulk transaction saves efficiently', function () {
        $account = Factories::loyaltyAccount();
        $this->accountRepository->save($account);

        $transactions = [];
        for ($i = 0; $i < 20; $i++) {
            $transactions[] = Factories::pointsTransaction($account->getId());
        }
        $this->transactionRepository->saveMany($transactions);

        $list = $this->transactionRepository->findByAccountId($account->getId(), 50);
        expect(count($list))->toBe(20);
    });

    it('calculates aggregate statistics correctly', function () {
        $account = Factories::loyaltyAccount();
        $this->accountRepository->save($account);

        // 3 EARN transactions
        $txs = [];
        for ($i = 0; $i < 3; $i++) {
            $txs[] = Factories::pointsTransaction($account->getId());
        }
        // 2 REDEEM transactions
        for ($i = 0; $i < 2; $i++) {
            $t = Factories::pointsTransaction($account->getId(), TransactionType::REDEEM);
            $txs[] = $t;
        }
        $this->transactionRepository->saveMany($txs);

        expect($this->transactionRepository->getTotalTransactions())->toBeGreaterThanOrEqual(5);
        expect($this->transactionRepository->getTotalPointsEarned())->toBeGreaterThan(0);
        expect($this->transactionRepository->getTotalPointsRedeemed())->toBeGreaterThan(0);
    });

    it('filters transactions by type correctly', function () {
        $account = Factories::loyaltyAccount();
        $this->accountRepository->save($account);

        $earn = Factories::pointsTransaction($account->getId(), TransactionType::EARN, Factories::points(100));
        $redeem = Factories::pointsTransaction($account->getId(), TransactionType::REDEEM, Factories::points(50));
        $this->transactionRepository->saveMany([$earn, $redeem]);

        $earns = $this->transactionRepository->findByType(TransactionType::EARN, 10);
        $redeems = $this->transactionRepository->findByType(TransactionType::REDEEM, 10);

        expect(count($earns))->toBeGreaterThanOrEqual(1);
        expect(count($redeems))->toBeGreaterThanOrEqual(1);
    });

    it('finds transactions by account efficiently', function () {
        $account = Factories::loyaltyAccount();
        $this->accountRepository->save($account);
        $this->transactionRepository->save(Factories::pointsTransaction($account->getId()));

        $list = $this->transactionRepository->findByAccountId($account->getId());
        expect($list)->not->toBeEmpty();
    });

    it('finds transactions by date range accurately', function () {
        $account = Factories::loyaltyAccount();
        $this->accountRepository->save($account);
        $this->transactionRepository->save(Factories::pointsTransaction($account->getId()));

        $from = (new DateTimeImmutable)->modify('-1 day');
        $to = (new DateTimeImmutable)->modify('+1 day');
        $list = $this->transactionRepository->findByDateRange($from, $to);
        expect($list)->not->toBeEmpty();
    });
})->uses(DatabaseTestCase::class);

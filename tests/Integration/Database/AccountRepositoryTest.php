<?php

use LoyaltyRewards\Core\Exceptions\AccountNotFoundException;
use LoyaltyRewards\Tests\Support\DatabaseTestCase;
use LoyaltyRewards\Tests\Support\Factories;

describe('Database Account Repository Integration', function () {
    it('persists and retrieves account correctly', function () {
        $account = Factories::loyaltyAccount();
        $this->accountRepository->save($account);

        $found = $this->accountRepository->findById($account->getId());
        expect($found->getCustomerId()->toString())->toBe($account->getCustomerId()->toString());
    });

    it('updates existing account correctly', function () {
        $account = Factories::loyaltyAccount();
        $this->accountRepository->save($account);

        // add some pending points then confirm
        $tx = $account->earnPoints(Factories::points(100), Factories::transactionContext(['x' => 1]));
        $account->confirmPendingPoints();
        $this->accountRepository->save($account);

        $found = $this->accountRepository->findById($account->getId());
        expect($found->getAvailablePoints()->value())->toBeGreaterThan(0);
    });

    it('throws exception for non-existent account', function () {
        expect(fn () => $this->accountRepository->findById(Factories::accountId()))
            ->toThrow(AccountNotFoundException::class);
    });

    it('finds inactive accounts correctly', function () {
        $old = Factories::loyaltyAccount();
        $this->accountRepository->save($old);

        $createdAt = (new DateTimeImmutable)->modify('-7 day')->format('Y-m-d H:i:s');
        $lastActivityAt = (new DateTimeImmutable)->modify('-5 day')->format('Y-m-d H:i:s');

        // backdate last_activity and created_at
        $stmt = $this->pdo->prepare(
            'UPDATE loyalty_accounts SET created_at = :created_at, last_activity_at = :last_activity_at WHERE id = :id'
        );
        $stmt->execute([
            'created_at' => $createdAt,
            'last_activity_at' => $lastActivityAt,
            'id' => $old->getId()->toString(),
        ]);

        $recent = Factories::loyaltyAccount();
        $this->accountRepository->save($recent);

        $since = (new DateTimeImmutable)->modify('-2 days');
        $inactive = $this->accountRepository->findInactive($since);
        expect($inactive)->not->toBeEmpty();
    });

    it('handles batch operations efficiently', function () {
        for ($i = 0; $i < 10; $i++) {
            $this->accountRepository->save(Factories::loyaltyAccount());
        }
        expect($this->accountRepository->getTotalAccounts())->toBeGreaterThanOrEqual(10);
    });

    it('handles concurrent access safely', function () {
        // simulate by rapid successive saves
        $account = Factories::loyaltyAccount();
        for ($i = 0; $i < 5; $i++) {
            $this->accountRepository->save($account);
        }
        $found = $this->accountRepository->findByCustomerId($account->getCustomerId());
        expect($found->getId()->toString())->toBe($account->getId()->toString());
    });
})->uses(DatabaseTestCase::class);

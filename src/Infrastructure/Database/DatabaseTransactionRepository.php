<?php

declare(strict_types=1);

namespace LoyaltyRewards\Infrastructure\Database;

use LoyaltyRewards\Domain\Repositories\TransactionRepositoryInterface;
use LoyaltyRewards\Domain\Models\PointsTransaction;
use LoyaltyRewards\Domain\ValueObjects\{TransactionId, AccountId, CustomerId, Points, TransactionContext};
use LoyaltyRewards\Domain\Enums\TransactionType;
use DateTimeImmutable;
use PDO;

readonly class DatabaseTransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function findById(TransactionId $id): ?PointsTransaction
    {
        $sql = "SELECT * FROM points_transactions WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id->toString()]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRowToTransaction($row) : null;
    }

    public function findByAccountId(AccountId $accountId, int $limit = 100): array
    {
        $sql = "
            SELECT * FROM points_transactions 
            WHERE account_id = :account_id 
            ORDER BY created_at DESC 
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('account_id', $accountId->toString());
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $transactions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transactions[] = $this->mapRowToTransaction($row);
        }

        return $transactions;
    }

    public function findByCustomerId(CustomerId $customerId, int $limit = 100): array
    {
        $sql = "
            SELECT pt.* FROM points_transactions pt
            JOIN loyalty_accounts la ON pt.account_id = la.id
            WHERE la.customer_id = :customer_id
            ORDER BY pt.created_at DESC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('customer_id', $customerId->toString());
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $transactions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transactions[] = $this->mapRowToTransaction($row);
        }

        return $transactions;
    }

    public function findByType(TransactionType $type, int $limit = 100): array
    {
        $sql = "
            SELECT * FROM points_transactions 
            WHERE type = :type 
            ORDER BY created_at DESC 
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('type', $type->value);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $transactions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transactions[] = $this->mapRowToTransaction($row);
        }

        return $transactions;
    }

    public function findByDateRange(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $limit = 1000
    ): array {
        $sql = "
            SELECT * FROM points_transactions 
            WHERE created_at BETWEEN :from AND :to 
            ORDER BY created_at DESC 
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('from', $from->format('Y-m-d H:i:s'));
        $stmt->bindValue('to', $to->format('Y-m-d H:i:s'));
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $transactions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transactions[] = $this->mapRowToTransaction($row);
        }

        return $transactions;
    }

    public function findByAccountAndDateRange(
        AccountId $accountId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $limit = 100
    ): array {
        $sql = "
            SELECT * FROM points_transactions 
            WHERE account_id = :account_id 
              AND created_at BETWEEN :from AND :to 
            ORDER BY created_at DESC 
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('account_id', $accountId->toString());
        $stmt->bindValue('from', $from->format('Y-m-d H:i:s'));
        $stmt->bindValue('to', $to->format('Y-m-d H:i:s'));
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $transactions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transactions[] = $this->mapRowToTransaction($row);
        }

        return $transactions;
    }

    public function save(PointsTransaction $transaction): void
    {
        $sql = "
            INSERT INTO points_transactions (
                id, account_id, type, points, context_data, created_at, processed_at
            ) VALUES (
                :id, :account_id, :type, :points, :context_data, :created_at, :processed_at
            ) ON CONFLICT (id) DO UPDATE SET
                processed_at = EXCLUDED.processed_at
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $transaction->getId()->toString(),
            'account_id' => $transaction->getAccountId()->toString(),
            'type' => $transaction->getType()->value,
            'points' => $transaction->getPoints()->value(),
            'context_data' => json_encode($transaction->getContext()->toArray()),
            'created_at' => $transaction->getCreatedAt()->format('Y-m-d H:i:s'),
            'processed_at' => $transaction->getProcessedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    public function saveMany(array $transactions): void
    {
        if (empty($transactions)) {
            return;
        }

        $this->pdo->beginTransaction();

        try {
            foreach ($transactions as $transaction) {
                $this->save($transaction);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findPendingTransactions(): array
    {
        $sql = "SELECT * FROM points_transactions WHERE processed_at IS NULL ORDER BY created_at";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $transactions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transactions[] = $this->mapRowToTransaction($row);
        }

        return $transactions;
    }

    public function getTotalTransactions(): int
    {
        $sql = "SELECT COUNT(*) FROM points_transactions";
        $stmt = $this->pdo->query($sql);

        return (int) $stmt->fetchColumn();
    }

    public function getTotalPointsEarned(): int
    {
        $sql = "SELECT COALESCE(SUM(points), 0) FROM points_transactions WHERE type = 'earn'";
        $stmt = $this->pdo->query($sql);

        return (int) $stmt->fetchColumn();
    }

    public function getTotalPointsRedeemed(): int
    {
        $sql = "SELECT COALESCE(SUM(ABS(points)), 0) FROM points_transactions WHERE type = 'redeem'";
        $stmt = $this->pdo->query($sql);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function mapRowToTransaction(array $row): PointsTransaction
    {
        $contextData = json_decode($row['context_data'], true) ?? [];
        $context = TransactionContext::create($contextData['data'] ?? []);

        return new PointsTransaction(
            TransactionId::fromString($row['id']),
            AccountId::fromString($row['account_id']),
            TransactionType::from($row['type']),
            Points::fromInt($row['points']),
            $context,
            new DateTimeImmutable($row['created_at']),
            $row['processed_at'] ? new DateTimeImmutable($row['processed_at']) : null
        );
    }
}

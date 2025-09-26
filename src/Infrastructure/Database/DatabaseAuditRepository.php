<?php

declare(strict_types=1);

namespace LoyaltyRewards\Infrastructure\Database;

use LoyaltyRewards\Domain\Repositories\AuditRepositoryInterface;
use LoyaltyRewards\Infrastructure\Audit\AuditRecord;
use LoyaltyRewards\Domain\ValueObjects\{AccountId, CustomerId};
use DateTimeImmutable;
use PDO;

class DatabaseAuditRepository implements AuditRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    public function store(AuditRecord $record): void
    {
        $sql = "
            INSERT INTO audit_logs (
                entity_type, entity_id, action, user_id, data, 
                ip_address, user_agent, created_at
            ) VALUES (
                :entity_type, :entity_id, :action, :user_id, :data,
                :ip_address, :user_agent, :created_at
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'entity_type' => $record->entityType,
            'entity_id' => $record->entityId,
            'action' => $record->action,
            'user_id' => $record->userId,
            'data' => json_encode($record->data),
            'ip_address' => $record->ipAddress,
            'user_agent' => $record->userAgent,
            'created_at' => $record->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function storeMany(array $records): void
    {
        if (empty($records)) {
            return;
        }

        $this->pdo->beginTransaction();

        try {
            foreach ($records as $record) {
                $this->store($record);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findByAccount(AccountId $accountId, int $limit = 100): array
    {
        $sql = "
            SELECT * FROM audit_logs 
            WHERE entity_type = 'loyalty_account' AND entity_id = :entity_id 
            ORDER BY created_at DESC 
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('entity_id', $accountId->toString());
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $this->mapRowsToRecords($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findByCustomer(CustomerId $customerId, int $limit = 100): array
    {
        $sql = "
            SELECT * FROM audit_logs 
            WHERE entity_type = 'loyalty_customer' AND entity_id = :entity_id 
            ORDER BY created_at DESC 
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('entity_id', $customerId->toString());
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $this->mapRowsToRecords($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findByDateRange(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $limit = 1000
    ): array {
        $sql = "
            SELECT * FROM audit_logs 
            WHERE created_at BETWEEN :from AND :to 
            ORDER BY created_at DESC 
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('from', $from->format('Y-m-d H:i:s'));
        $stmt->bindValue('to', $to->format('Y-m-d H:i:s'));
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $this->mapRowsToRecords($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findByAction(string $action, int $limit = 100): array
    {
        $sql = "
            SELECT * FROM audit_logs 
            WHERE action = :action 
            ORDER BY created_at DESC 
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('action', $action);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $this->mapRowsToRecords($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function deleteOlderThan(DateTimeImmutable $date): int
    {
        $sql = "DELETE FROM audit_logs WHERE created_at < :date";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['date' => $date->format('Y-m-d H:i:s')]);

        return $stmt->rowCount();
    }

    private function mapRowsToRecords(array $rows): array
    {
        return array_map(fn($row) => $this->mapRowToRecord($row), $rows);
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function mapRowToRecord(array $row): AuditRecord
    {
        return new AuditRecord(
            $row['entity_type'],
            $row['entity_id'],
            $row['action'],
            $row['user_id'],
            json_decode($row['data'], true) ?? [],
            $row['ip_address'],
            $row['user_agent'],
            new DateTimeImmutable($row['created_at'])
        );
    }
}

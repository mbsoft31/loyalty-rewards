<?php

declare(strict_types=1);

namespace LoyaltyRewards\Tests\Support;

use LoyaltyRewards\Infrastructure\Database\{
    DatabaseConnectionFactory,
    DatabaseAccountRepository,
    DatabaseTransactionRepository,
    DatabaseAuditRepository
};
use PDO;
use PHPUnit\Framework\TestCase;
use Mockery;

abstract class DatabaseTestCase extends TestCase
{
    protected ?PDO $pdo;
    protected DatabaseAccountRepository $accountRepository;
    protected DatabaseTransactionRepository $transactionRepository;
    protected DatabaseAuditRepository $auditRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = DatabaseConnectionFactory::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->createTables();
        $this->createIndexes();
        $this->seedTestData();
        $this->setupRepositories();
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        Mockery::close();
        parent::tearDown();
    }

    private function createTables(): void
    {
        // Create loyalty_accounts table
        $this->pdo->exec('
            CREATE TABLE loyalty_accounts (
                id TEXT PRIMARY KEY,
                customer_id TEXT UNIQUE NOT NULL,
                available_points INTEGER NOT NULL DEFAULT 0 CHECK (available_points >= 0),
                pending_points INTEGER NOT NULL DEFAULT 0 CHECK (pending_points >= 0),
                lifetime_points INTEGER NOT NULL DEFAULT 0 CHECK (lifetime_points >= 0),
                status TEXT NOT NULL DEFAULT "active" CHECK (status IN ("active", "inactive", "suspended", "closed")),
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_activity_at TEXT
            )
        ');

        // Create points_transactions table
        $this->pdo->exec('
            CREATE TABLE points_transactions (
                id TEXT PRIMARY KEY,
                account_id TEXT NOT NULL,
                type TEXT NOT NULL CHECK (type IN ("earn", "redeem", "expire", "refund", "adjustment")),
                points INTEGER NOT NULL,
                context_data TEXT,
                created_at TEXT NOT NULL,
                processed_at TEXT,
                FOREIGN KEY (account_id) REFERENCES loyalty_accounts(id) ON DELETE CASCADE
            )
        ');

        // Create audit_logs table
        $this->pdo->exec('
            CREATE TABLE audit_logs (
                id TEXT PRIMARY KEY DEFAULT (lower(hex(randomblob(16)))),
                entity_type TEXT NOT NULL,
                entity_id TEXT NOT NULL,
                action TEXT NOT NULL,
                user_id TEXT,
                data TEXT,
                ip_address TEXT,
                user_agent TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Create fraud_detection_logs table
        $this->pdo->exec('
            CREATE TABLE fraud_detection_logs (
                id TEXT PRIMARY KEY DEFAULT (lower(hex(randomblob(16)))),
                account_id TEXT NOT NULL,
                customer_id TEXT NOT NULL,
                fraud_score REAL NOT NULL,
                reasons TEXT,
                transaction_amount INTEGER,
                transaction_currency TEXT,
                context_data TEXT,
                action_taken TEXT NOT NULL DEFAULT "none" CHECK (action_taken IN ("none", "flagged", "blocked", "suspended")),
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (account_id) REFERENCES loyalty_accounts(id) ON DELETE CASCADE
            )
        ');
    }

    private function createIndexes(): void
    {
        // Indexes for better performance
        $indexes = [
            'CREATE INDEX idx_accounts_customer_id ON loyalty_accounts(customer_id)',
            'CREATE INDEX idx_accounts_status ON loyalty_accounts(status)',
            'CREATE INDEX idx_accounts_last_activity ON loyalty_accounts(last_activity_at)',
            'CREATE INDEX idx_transactions_account_id ON points_transactions(account_id)',
            'CREATE INDEX idx_transactions_type ON points_transactions(type)',
            'CREATE INDEX idx_transactions_created_at ON points_transactions(created_at)',
            'CREATE INDEX idx_transactions_account_type ON points_transactions(account_id, type)',
            'CREATE INDEX idx_audit_entity_type_id ON audit_logs(entity_type, entity_id)',
            'CREATE INDEX idx_audit_action ON audit_logs(action)',
            'CREATE INDEX idx_audit_created_at ON audit_logs(created_at)',
        ];

        foreach ($indexes as $index) {
            $this->pdo->exec($index);
        }
    }

    protected function seedTestData(): void
    {
        // Override in specific test classes if needed
    }

    private function setupRepositories(): void
    {
        $this->transactionRepository = new DatabaseTransactionRepository($this->pdo);
        $this->accountRepository = new DatabaseAccountRepository($this->pdo, $this->transactionRepository);
        $this->auditRepository = new DatabaseAuditRepository($this->pdo);
    }

    protected function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    protected function rollback(): void
    {
        $this->pdo->rollBack();
    }

    protected function commit(): void
    {
        $this->pdo->commit();
    }

    protected function truncateAllTables(): void
    {
        $this->pdo->exec('DELETE FROM points_transactions');
        $this->pdo->exec('DELETE FROM audit_logs');
        $this->pdo->exec('DELETE FROM fraud_detection_logs');
        $this->pdo->exec('DELETE FROM loyalty_accounts');
    }

    protected function getTableRowCount(string $table): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$table}");
        return (int) $stmt->fetchColumn();
    }
}

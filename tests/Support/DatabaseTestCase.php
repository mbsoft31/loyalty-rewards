<?php

declare(strict_types=1);

namespace LoyaltyRewards\Tests\Support;

use LoyaltyRewards\Infrastructure\Database\DatabaseAccountRepository;
use LoyaltyRewards\Infrastructure\Database\DatabaseAuditRepository;
use LoyaltyRewards\Infrastructure\Database\DatabaseConnectionFactory;
use LoyaltyRewards\Infrastructure\Database\DatabaseTransactionRepository;
use Mockery;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected ?PDO $pdo;

    protected string $driver = 'sqlite';

    protected DatabaseAccountRepository $accountRepository;

    protected DatabaseTransactionRepository $transactionRepository;

    protected DatabaseAuditRepository $auditRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = (string) getenv('DB_CONNECTION') ?: 'sqlite';

        $config = [
            'driver' => $this->driver,
        ];

        if ($this->driver === 'sqlite') {
            $config['database'] = (string) (getenv('DB_DATABASE') ?: ':memory:');
        } else {
            $config['host'] = (string) (getenv('DB_HOST') ?: '127.0.0.1');
            $config['port'] = (int) (getenv('DB_PORT') ?: ($this->driver === 'mysql' ? 3306 : 5432));
            $config['database'] = (string) (getenv('DB_DATABASE') ?: 'loyalty_test');
            $config['username'] = (string) (getenv('DB_USERNAME') ?: 'root');
            $config['password'] = (string) (getenv('DB_PASSWORD') ?: '');
        }

        $this->pdo = DatabaseConnectionFactory::create($config);

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
        if ($this->driver === 'sqlite') {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS loyalty_accounts (
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

            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS points_transactions (
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

            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS audit_logs (
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

            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS fraud_detection_logs (
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

            return;
        }

        if ($this->driver === 'pgsql') {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS loyalty_accounts (
                    id UUID PRIMARY KEY,
                    customer_id VARCHAR(255) UNIQUE NOT NULL,
                    available_points INTEGER NOT NULL DEFAULT 0 CHECK (available_points >= 0),
                    pending_points INTEGER NOT NULL DEFAULT 0 CHECK (pending_points >= 0),
                    lifetime_points INTEGER NOT NULL DEFAULT 0 CHECK (lifetime_points >= 0),
                    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active','inactive','suspended','closed')),
                    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_activity_at TIMESTAMPTZ
                )"
            );

            $this->createIndexIfNotExists('idx_accounts_customer_id', 'loyalty_accounts', 'customer_id');
            $this->createIndexIfNotExists('idx_accounts_status', 'loyalty_accounts', 'status');
            $this->createIndexIfNotExists('idx_accounts_last_activity', 'loyalty_accounts', 'last_activity_at');

            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS points_transactions (
                    id UUID PRIMARY KEY,
                    account_id UUID NOT NULL,
                    type VARCHAR(20) NOT NULL CHECK (type IN ('earn','redeem','expire','refund','adjustment')),
                    points INTEGER NOT NULL,
                    context_data JSON,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    processed_at TIMESTAMPTZ,
                    FOREIGN KEY (account_id) REFERENCES loyalty_accounts(id) ON DELETE CASCADE
                )"
            );
            $this->createIndexIfNotExists('idx_transactions_account_id', 'points_transactions', 'account_id');
            $this->createIndexIfNotExists('idx_transactions_type', 'points_transactions', 'type');
            $this->createIndexIfNotExists('idx_transactions_created_at', 'points_transactions', 'created_at');
            $this->createIndexIfNotExists('idx_transactions_account_type', 'points_transactions', 'account_id, type');

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS audit_logs (
                    id UUID PRIMARY KEY,
                    entity_type VARCHAR(50) NOT NULL,
                    entity_id VARCHAR(255) NOT NULL,
                    action VARCHAR(50) NOT NULL,
                    user_id VARCHAR(255),
                    data JSON,
                    ip_address INET,
                    user_agent TEXT,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
                )'
            );
            $this->createIndexIfNotExists('idx_audit_entity_type_id', 'audit_logs', 'entity_type, entity_id');
            $this->createIndexIfNotExists('idx_audit_action', 'audit_logs', 'action');
            $this->createIndexIfNotExists('idx_audit_created_at', 'audit_logs', 'created_at');

            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS fraud_detection_logs (
                    id UUID PRIMARY KEY,
                    account_id UUID NOT NULL,
                    customer_id VARCHAR(255) NOT NULL,
                    fraud_score DECIMAL(5,4) NOT NULL,
                    reasons JSON,
                    transaction_amount INTEGER,
                    transaction_currency VARCHAR(3),
                    context_data JSON,
                    action_taken VARCHAR(20) NOT NULL DEFAULT 'none' CHECK (action_taken IN ('none','flagged','blocked','suspended')),
                    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (account_id) REFERENCES loyalty_accounts(id) ON DELETE CASCADE
                )"
            );
            $this->createIndexIfNotExists('idx_fraud_account_id', 'fraud_detection_logs', 'account_id');
            $this->createIndexIfNotExists('idx_fraud_customer_id', 'fraud_detection_logs', 'customer_id');
            $this->createIndexIfNotExists('idx_fraud_score', 'fraud_detection_logs', 'fraud_score');
            $this->createIndexIfNotExists('idx_fraud_action', 'fraud_detection_logs', 'action_taken');
            $this->createIndexIfNotExists('idx_fraud_created_at', 'fraud_detection_logs', 'created_at');

            return;
        }

        // mysql
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS loyalty_accounts (
                id CHAR(36) PRIMARY KEY,
                customer_id VARCHAR(255) NOT NULL UNIQUE,
                available_points INT NOT NULL DEFAULT 0,
                pending_points INT NOT NULL DEFAULT 0,
                lifetime_points INT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_activity_at DATETIME NULL
            ) ENGINE=InnoDB"
        );
        $this->createIndexIfNotExists('idx_accounts_customer_id', 'loyalty_accounts', 'customer_id');
        $this->createIndexIfNotExists('idx_accounts_status', 'loyalty_accounts', 'status');
        $this->createIndexIfNotExists('idx_accounts_last_activity', 'loyalty_accounts', 'last_activity_at');

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS points_transactions (
                id CHAR(36) PRIMARY KEY,
                account_id CHAR(36) NOT NULL,
                type VARCHAR(20) NOT NULL,
                points INT NOT NULL,
                context_data JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                processed_at DATETIME NULL,
                CONSTRAINT fk_pt_account FOREIGN KEY (account_id) REFERENCES loyalty_accounts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB'
        );
        $this->createIndexIfNotExists('idx_transactions_account_id', 'points_transactions', 'account_id');
        $this->createIndexIfNotExists('idx_transactions_type', 'points_transactions', 'type');
        $this->createIndexIfNotExists('idx_transactions_created_at', 'points_transactions', 'created_at');
        $this->createIndexIfNotExists('idx_transactions_account_type', 'points_transactions', 'account_id, type');

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS audit_logs (
                id CHAR(36) PRIMARY KEY,
                entity_type VARCHAR(50) NOT NULL,
                entity_id VARCHAR(255) NOT NULL,
                action VARCHAR(50) NOT NULL,
                user_id VARCHAR(255) NULL,
                data JSON NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB'
        );
        $this->createIndexIfNotExists('idx_audit_entity_type_id', 'audit_logs', 'entity_type, entity_id');
        $this->createIndexIfNotExists('idx_audit_action', 'audit_logs', 'action');
        $this->createIndexIfNotExists('idx_audit_created_at', 'audit_logs', 'created_at');

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS fraud_detection_logs (
                id CHAR(36) PRIMARY KEY,
                account_id CHAR(36) NOT NULL,
                customer_id VARCHAR(255) NOT NULL,
                fraud_score DECIMAL(5,4) NOT NULL,
                reasons JSON NULL,
                transaction_amount INT NULL,
                transaction_currency VARCHAR(3) NULL,
                context_data JSON NULL,
                action_taken VARCHAR(20) NOT NULL DEFAULT 'none',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_fraud_account FOREIGN KEY (account_id) REFERENCES loyalty_accounts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        $this->createIndexIfNotExists('idx_fraud_account_id', 'fraud_detection_logs', 'account_id');
        $this->createIndexIfNotExists('idx_fraud_customer_id', 'fraud_detection_logs', 'customer_id');
        $this->createIndexIfNotExists('idx_fraud_score', 'fraud_detection_logs', 'fraud_score');
        $this->createIndexIfNotExists('idx_fraud_action', 'fraud_detection_logs', 'action_taken');
        $this->createIndexIfNotExists('idx_fraud_created_at', 'fraud_detection_logs', 'created_at');
    }

    private function createIndexes(): void
    {
        // For sqlite we create indexes here
        if ($this->driver === 'sqlite') {
            $indexes = [
                'CREATE INDEX IF NOT EXISTS idx_accounts_customer_id ON loyalty_accounts(customer_id)',
                'CREATE INDEX IF NOT EXISTS idx_accounts_status ON loyalty_accounts(status)',
                'CREATE INDEX IF NOT EXISTS idx_accounts_last_activity ON loyalty_accounts(last_activity_at)',
                'CREATE INDEX IF NOT EXISTS idx_transactions_account_id ON points_transactions(account_id)',
                'CREATE INDEX IF NOT EXISTS idx_transactions_type ON points_transactions(type)',
                'CREATE INDEX IF NOT EXISTS idx_transactions_created_at ON points_transactions(created_at)',
                'CREATE INDEX IF NOT EXISTS idx_transactions_account_type ON points_transactions(account_id, type)',
                'CREATE INDEX IF NOT EXISTS idx_audit_entity_type_id ON audit_logs(entity_type, entity_id)',
                'CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_logs(action)',
                'CREATE INDEX IF NOT EXISTS idx_audit_created_at ON audit_logs(created_at)',
            ];
            foreach ($indexes as $index) {
                $this->pdo->exec($index);
            }
        }
        // pgsql/mysql handled during createTables
    }

    private function createIndexIfNotExists(string $indexName, string $table, string $columns): void
    {
        if ($this->driver === 'sqlite') {
            $this->pdo->exec(sprintf('CREATE INDEX IF NOT EXISTS %s ON %s (%s)', $indexName, $table, $columns));

            return;
        }

        if ($this->indexExists($indexName, $table)) {
            return;
        }

        $this->pdo->exec(sprintf('CREATE INDEX %s ON %s (%s)', $indexName, $table, $columns));
    }

    private function indexExists(string $indexName, string $table): bool
    {
        if ($this->driver === 'mysql') {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table_name AND index_name = :index_name'
            );
            $stmt->execute([
                'table_name' => $table,
                'index_name' => $indexName,
            ]);

            return (int) $stmt->fetchColumn() > 0;
        }

        if ($this->driver === 'pgsql') {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM pg_indexes WHERE tablename = :table_name AND indexname = :index_name'
            );
            $stmt->execute([
                'table_name' => $table,
                'index_name' => $indexName,
            ]);

            return (int) $stmt->fetchColumn() > 0;
        }

        return false;
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

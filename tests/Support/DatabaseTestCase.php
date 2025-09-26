<?php

declare(strict_types=1);

namespace LoyaltyRewards\Tests\Support;

use LoyaltyRewards\Infrastructure\Database\DatabaseConnectionFactory;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = DatabaseConnectionFactory::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->createTables();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        parent::tearDown();
    }

    private function createTables(): void
    {
        // Create loyalty_accounts table
        $this->pdo->exec('
            CREATE TABLE loyalty_accounts (
                id TEXT PRIMARY KEY,
                customer_id TEXT UNIQUE NOT NULL,
                available_points INTEGER NOT NULL DEFAULT 0,
                pending_points INTEGER NOT NULL DEFAULT 0,
                lifetime_points INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT "active",
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                last_activity_at TEXT
            )
        ');

        // Create points_transactions table
        $this->pdo->exec('
            CREATE TABLE points_transactions (
                id TEXT PRIMARY KEY,
                account_id TEXT NOT NULL,
                type TEXT NOT NULL,
                points INTEGER NOT NULL,
                context_data TEXT,
                created_at TEXT NOT NULL,
                processed_at TEXT,
                FOREIGN KEY (account_id) REFERENCES loyalty_accounts(id)
            )
        ');

        // Create audit_logs table
        $this->pdo->exec('
            CREATE TABLE audit_logs (
                id TEXT PRIMARY KEY,
                entity_type TEXT NOT NULL,
                entity_id TEXT NOT NULL,
                action TEXT NOT NULL,
                user_id TEXT,
                data TEXT,
                ip_address TEXT,
                user_agent TEXT,
                created_at TEXT NOT NULL
            )
        ');

        // Create fraud_detection_logs table
        $this->pdo->exec('
            CREATE TABLE fraud_detection_logs (
                id TEXT PRIMARY KEY,
                account_id TEXT NOT NULL,
                customer_id TEXT NOT NULL,
                fraud_score REAL NOT NULL,
                reasons TEXT,
                transaction_amount INTEGER,
                transaction_currency TEXT,
                context_data TEXT,
                action_taken TEXT NOT NULL DEFAULT "none",
                created_at TEXT NOT NULL,
                FOREIGN KEY (account_id) REFERENCES loyalty_accounts(id)
            )
        ');
    }

    protected function seedTestData(): void
    {
        // Override in specific test classes if needed
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
}

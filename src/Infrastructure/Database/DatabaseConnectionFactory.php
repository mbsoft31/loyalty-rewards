<?php

declare(strict_types=1);

namespace LoyaltyRewards\Infrastructure\Database;

use PDO;
use PDOException;

class DatabaseConnectionFactory
{
    public static function create(array $config): PDO
    {
        $dsn = self::buildDsn($config);

        try {
            $pdo = new PDO(
                $dsn,
                $config['username'] ?? null,
                $config['password'] ?? null,
                self::getDefaultOptions($config)
            );

            // Set error mode
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            return $pdo;
        } catch (PDOException $e) {
            throw new \RuntimeException("Database connection failed: {$e->getMessage()}", 0, $e);
        }
    }

    private static function buildDsn(array $config): string
    {
        $driver = $config['driver'] ?? 'pgsql';

        return match ($driver) {
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $config['host'] ?? 'localhost',
                $config['port'] ?? 5432,
                $config['database']
            ),
            'mysql' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host'] ?? 'localhost',
                $config['port'] ?? 3306,
                $config['database']
            ),
            'sqlite' => "sqlite:{$config['database']}",
            default => throw new \InvalidArgumentException("Unsupported database driver: {$driver}")
        };
    }

    private static function getDefaultOptions(array $config): array
    {
        $defaults = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        return array_merge($defaults, $config['options'] ?? []);
    }
}

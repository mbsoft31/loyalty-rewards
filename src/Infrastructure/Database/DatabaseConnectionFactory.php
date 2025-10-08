<?php

declare(strict_types=1);

namespace LoyaltyRewards\Infrastructure\Database;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

class DatabaseConnectionFactory
{
    public static function create(array $config): PDO
    {
        // Allow env fallbacks for CI/tests if not provided
        $env = static fn(string $key, $default = null) => (($v = getenv($key)) !== false ? $v : $default);
        $driver = $config['driver'] ?? $env('DB_CONNECTION', 'pgsql');
        $host = $config['host'] ?? $env('DB_HOST', 'localhost');
        $port = $config['port'] ?? $env('DB_PORT');
        $database = $config['database'] ?? $env('DB_DATABASE');
        $username = $config['username'] ?? $env('DB_USERNAME');
        $password = $config['password'] ?? $env('DB_PASSWORD');

        $dsn = self::buildDsn([
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'database' => $database,
        ]);

        $attempts = 0;
        $maxAttempts = 5;
        $lastException = null;

        while ($attempts < $maxAttempts) {
            try {
                $pdo = new PDO(
                    $dsn,
                    $username,
                    $password,
                    self::getDefaultOptions($config)
                );

                // Set error mode
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                return $pdo;
            } catch (PDOException $e) {
                $lastException = $e;
                $attempts++;
                usleep(250000); // wait 250ms before retry
            }
        }

        throw new RuntimeException("Database connection failed: {$lastException?->getMessage()}", 0, $lastException);
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
            default => throw new InvalidArgumentException("Unsupported database driver: {$driver}")
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

<?php

declare(strict_types=1);

namespace LoyaltyRewards\Infrastructure\Database;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

class DatabaseConnectionFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function create(array $config): PDO
    {
        // Allow env fallbacks for CI/tests if not provided
        $env = static fn (string $key, mixed $default = null): mixed => (($value = getenv($key)) !== false ? $value : $default);
        $driver = (string) ($config['driver'] ?? $env('DB_CONNECTION', 'pgsql'));
        $host = (string) ($config['host'] ?? $env('DB_HOST', 'localhost'));
        $port = $config['port'] ?? $env('DB_PORT');
        $database = (string) ($config['database'] ?? $env('DB_DATABASE', ''));
        $username = $config['username'] ?? $env('DB_USERNAME');
        $password = $config['password'] ?? $env('DB_PASSWORD');

        $username = $username === null ? null : (string) $username;
        $password = $password === null ? null : (string) $password;

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

        throw new RuntimeException("Database connection failed: {$lastException->getMessage()}", 0, $lastException);
    }

    /**
     * @param  array{driver: string, host?: string, port?: int|string|null, database?: string|null}  $config
     */
    private static function buildDsn(array $config): string
    {
        $driver = $config['driver'];
        $database = (string) ($config['database'] ?? '');

        return match ($driver) {
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $config['host'] ?? 'localhost',
                $config['port'] ?? 5432,
                $database
            ),
            'mysql' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host'] ?? 'localhost',
                $config['port'] ?? 3306,
                $database
            ),
            'sqlite' => "sqlite:{$database}",
            default => throw new InvalidArgumentException("Unsupported database driver: {$driver}")
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, mixed>
     */
    private static function getDefaultOptions(array $config): array
    {
        $defaults = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        $options = $config['options'] ?? [];
        if (! is_array($options)) {
            throw new InvalidArgumentException('Database options must be an array.');
        }

        foreach ($options as $key => $value) {
            if (! is_int($key)) {
                throw new InvalidArgumentException('Database option keys must be PDO attribute integers.');
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }
}

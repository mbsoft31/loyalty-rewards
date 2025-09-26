<?php

declare(strict_types=1);

namespace LoyaltyRewards\Tests\Support;

use LoyaltyRewards\Tests\Support\{DatabaseTestCase, BenchmarkRunner};

abstract class PerformanceTestCase extends DatabaseTestCase
{
    protected BenchmarkRunner $benchmark;
    protected array $performanceMetrics = [];

    // Performance thresholds (can be adjusted based on requirements)
    protected const PERFORMANCE_THRESHOLDS = [
        'single_transaction_max_time' => 0.1, // 100ms
        'bulk_transaction_max_time_per_item' => 0.01, // 10ms per item
        'memory_usage_max_mb' => 50, // 50MB
        'database_query_max_time' => 0.05, // 50ms
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->benchmark = new BenchmarkRunner();

        // Enable more detailed memory tracking
        if (function_exists('memory_get_peak_usage')) {
            $this->benchmark->enableMemoryTracking();
        }
    }

    protected function tearDown(): void
    {
        // Log performance metrics
        if (!empty($this->performanceMetrics)) {
            $this->logPerformanceMetrics();
        }

        parent::tearDown();
    }

    protected function benchmarkOperation(string $name, callable $operation, array $context = []): mixed
    {
        return $this->benchmark->measure($name, $operation, $context);
    }

    protected function assertPerformance(string $metricName, float $actualValue, float $threshold): void
    {
        $this->performanceMetrics[$metricName] = [
            'actual' => $actualValue,
            'threshold' => $threshold,
            'passed' => $actualValue <= $threshold,
        ];

        $this->assertLessThanOrEqual(
            $threshold,
            $actualValue,
            "Performance threshold exceeded for {$metricName}. Expected <= {$threshold}, got {$actualValue}"
        );
    }

    protected function assertMemoryUsage(float $maxMB): void
    {
        $memoryMB = memory_get_peak_usage(true) / 1024 / 1024;

        $this->assertPerformance('memory_usage_mb', $memoryMB, $maxMB);
    }

    protected function logPerformanceMetrics(): void
    {
        $logFile = __DIR__ . '/../../storage/logs/performance.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $testClass = get_class($this);

        $logEntry = [
            'timestamp' => $timestamp,
            'test_class' => $testClass,
            'metrics' => $this->performanceMetrics,
        ];

        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
}

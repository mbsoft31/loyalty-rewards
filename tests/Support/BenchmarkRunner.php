<?php

declare(strict_types=1);

namespace LoyaltyRewards\Tests\Support;

class BenchmarkRunner
{
    private bool $memoryTrackingEnabled = false;
    private array $results = [];

    public function enableMemoryTracking(): void
    {
        $this->memoryTrackingEnabled = true;
    }

    public function measure(string $name, callable $operation, array $context = []): mixed
    {
        $startTime = microtime(true);
        $startMemory = $this->memoryTrackingEnabled ? memory_get_usage(true) : 0;

        // Execute the operation
        $result = $operation();

        $endTime = microtime(true);
        $endMemory = $this->memoryTrackingEnabled ? memory_get_usage(true) : 0;

        // Calculate metrics
        $executionTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;

        $this->results[$name] = [
            'execution_time' => $executionTime,
            'memory_used' => $memoryUsed,
            'context' => $context,
            'timestamp' => microtime(true),
        ];

        return $result;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getResult(string $name): ?array
    {
        return $this->results[$name] ?? null;
    }

    public function clearResults(): void
    {
        $this->results = [];
    }

    public function measureMultiple(string $baseName, callable $operation, int $iterations): array
    {
        $results = [];

        for ($i = 0; $i < $iterations; $i++) {
            $name = "{$baseName}_iteration_{$i}";
            $results[] = $this->measure($name, $operation, ['iteration' => $i]);
        }

        return [
            'iterations' => $iterations,
            'total_time' => array_sum(array_column($this->results, 'execution_time')),
            'avg_time' => array_sum(array_column($this->results, 'execution_time')) / $iterations,
            'min_time' => min(array_column($this->results, 'execution_time')),
            'max_time' => max(array_column($this->results, 'execution_time')),
            'results' => $results,
        ];
    }
}

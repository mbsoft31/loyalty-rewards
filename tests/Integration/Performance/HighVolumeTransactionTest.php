<?php

use LoyaltyRewards\Core\Services\LoyaltyService;
use LoyaltyRewards\Core\Engine\RulesEngine;
use LoyaltyRewards\Core\Services\{FraudDetectionService, AuditService};
use LoyaltyRewards\Rules\Earning\CategoryMultiplierRule;
use LoyaltyRewards\Domain\ValueObjects\{ConversionRate, Currency};
use LoyaltyRewards\Tests\Support\{PerformanceTestCase, DataGenerator, Factories};
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

describe('High Volume Transaction Performance', function () {
    beforeEach(function () {
        $this->truncateAllTables();

        // Setup loyalty service with minimal dependencies
        $rulesEngine = new RulesEngine(new NullLogger());
        $rulesEngine->addEarningRule(new CategoryMultiplierRule(
            'electronics',
            2.0,
            ConversionRate::standard()
        ));

        $fraudDetection = mock(FraudDetectionService::class);
        $fraudResult = new \LoyaltyRewards\Core\Services\FraudDetection\FraudResult(0.1, []);
        $fraudDetection->shouldReceive('analyze')->andReturn($fraudResult);

        $auditService = mock(AuditService::class);
        $auditService->shouldReceive('logPointsEarned')->andReturn(null);
        $auditService->shouldReceive('logAccountCreated')->andReturn(null);

        $eventDispatcher = mock(EventDispatcherInterface::class);
        $eventDispatcher->shouldReceive('dispatch')->andReturn(null);

        $this->loyaltyService = new LoyaltyService(
            $this->accountRepository,
            $rulesEngine,
            $fraudDetection,
            $auditService,
            $eventDispatcher,
            new NullLogger()
        );
    });

    it('handles 1000 earning transactions within time threshold', function () {
        // Generate test data
        $bulkData = DataGenerator::generateBulkEarningData(1000);

        // Create accounts first
        foreach ($bulkData as $data) {
            $this->loyaltyService->createAccount($data['customer_id']);
        }

        // Benchmark bulk earning operations
        $result = $this->benchmarkOperation('bulk_earning_1000', function () use ($bulkData) {
            foreach ($bulkData as $data) {
                $this->loyaltyService->earnPoints(
                    $data['customer_id'],
                    $data['amount'],
                    $data['context']
                );
            }
            return count($bulkData);
        });

        $executionTime = $this->benchmark->getResult('bulk_earning_1000')['execution_time'];
        $avgTimePerTransaction = $executionTime / 1000;

        // Performance assertions
        $this->assertPerformance('bulk_transaction_avg_time', $avgTimePerTransaction, 0.01);
        $this->assertMemoryUsage(100); // Max 100MB for 1000 transactions

        // Verify all transactions were processed
        expect($this->getTableRowCount('loyalty_accounts'))->toBe(1000);
        expect($this->getTableRowCount('points_transactions'))->toBe(1000);
    });

    it('maintains performance under sustained load', function () {
        // Create 100 accounts
        $accounts = [];
        for ($i = 0; $i < 100; $i++) {
            $customerId = Factories::customerId("sustained_customer_{$i}");
            $accounts[] = $customerId;
            $this->loyaltyService->createAccount($customerId);
        }

        // Perform sustained operations (10 transactions per account)
        $operationCount = 0;
        $startTime = microtime(true);

        for ($round = 0; $round < 10; $round++) {
            foreach ($accounts as $customerId) {
                $this->loyaltyService->earnPoints(
                    $customerId,
                    Factories::money(rand(10, 100)),
                    Factories::transactionContext(['category' => 'electronics', 'round' => $round])
                );
                $operationCount++;
            }
        }

        $totalTime = microtime(true) - $startTime;
        $avgTimePerOperation = $totalTime / $operationCount;

        // Should maintain consistent performance throughout
        $this->assertPerformance('sustained_load_avg_time', $avgTimePerOperation, 0.02);

        expect($operationCount)->toBe(1000);
        expect($this->getTableRowCount('points_transactions'))->toBe(1000);
    });

    it('handles memory efficiently with large datasets', function () {
        $initialMemory = memory_get_usage(true);

        // Process 500 transactions
        for ($i = 0; $i < 500; $i++) {
            $customerId = Factories::customerId("memory_test_{$i}");
            $this->loyaltyService->createAccount($customerId);

            $this->loyaltyService->earnPoints(
                $customerId,
                Factories::money(50.0),
                Factories::transactionContext(['iteration' => $i])
            );

            // Force garbage collection every 100 iterations
            if ($i % 100 === 0) {
                gc_collect_cycles();
            }
        }

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = ($finalMemory - $initialMemory) / 1024 / 1024; // MB

        // Memory increase should be reasonable (less than 50MB for 500 transactions)
        $this->assertPerformance('memory_increase_mb', $memoryIncrease, 50);
    });

    it('benchmarks database query performance', function () {
        // Setup test data
        $account = Factories::loyaltyAccount();
        $this->accountRepository->save($account);

        // Create 100 transactions
        $transactions = [];
        for ($i = 0; $i < 100; $i++) {
            $transactions[] = Factories::pointsTransaction($account->getId());
        }
        $this->transactionRepository->saveMany($transactions);

        // Benchmark various query operations
        $queryBenchmarks = [
            'find_by_account' => fn() => $this->transactionRepository->findByAccountId($account->getId()),
            'find_by_type' => fn() => $this->transactionRepository->findByType(\LoyaltyRewards\Domain\Enums\TransactionType::EARN),
            'aggregate_totals' => fn() => $this->transactionRepository->getTotalTransactions(),
        ];

        foreach ($queryBenchmarks as $queryName => $queryFunction) {
            $this->benchmarkOperation("query_{$queryName}", $queryFunction);

            $queryTime = $this->benchmark->getResult("query_{$queryName}")['execution_time'];
            $this->assertPerformance("query_{$queryName}_time", $queryTime, 0.05);
        }
    });
})->uses(PerformanceTestCase::class);

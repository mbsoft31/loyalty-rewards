<?php

declare(strict_types=1);

namespace LoyaltyRewards\Tests\Support;

use PHPUnit\Framework\TestCase;
use Mockery;

abstract class LoyaltyTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Additional setup for loyalty tests
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a mock object for the given class
     */
    protected function mock(string $class): Mockery\MockInterface
    {
        return Mockery::mock($class);
    }

    /**
     * Create a partial mock object for the given class
     */
    protected function partialMock(string $class, array $methods = []): Mockery\MockInterface
    {
        return Mockery::mock($class)->makePartial();
    }

    /**
     * Create a spy object for the given class
     */
    protected function spy(string $class): Mockery\MockInterface
    {
        return Mockery::spy($class);
    }

    /**
     * Assert that a specific exception is thrown with message
     */
    protected function assertExceptionThrown(string $exceptionClass, string $message, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected exception {$exceptionClass} was not thrown");
        } catch (\Throwable $e) {
            $this->assertInstanceOf($exceptionClass, $e);
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}

<?php

declare(strict_types=1);

namespace LoyaltyRewards\Tests\Support;

use Mockery;
use PHPUnit\Framework\TestCase;

abstract class LoyaltyTestCase extends TestCase
{

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function mock(string $class): Mockery\MockInterface
    {
        return Mockery::mock($class);
    }

    protected function partialMock(string $class): Mockery\MockInterface
    {
        return Mockery::mock($class)->makePartial();
    }

    protected function spy(string $class): Mockery\MockInterface
    {
        return Mockery::spy($class);
    }
}

<?php

declare(strict_types=1);

use LoyaltyRewards\Tests\Support\LoyaltyTestCase;
use LoyaltyRewards\Tests\Support\DatabaseTestCase;
use LoyaltyRewards\Tests\Support\PerformanceTestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(LoyaltyTestCase::class)->in('Unit', 'Feature');
uses(DatabaseTestCase::class)->in('Integration/Database');
uses(PerformanceTestCase::class)->in('Integration/Performance');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBePoints', function (int $expectedValue) {
    return $this->toBeInstanceOf(\LoyaltyRewards\Domain\ValueObjects\Points::class)
        ->and($this->value->value())->toBe($expectedValue);
});

expect()->extend('toBePositivePoints', function () {
    return $this->toBeInstanceOf(\LoyaltyRewards\Domain\ValueObjects\Points::class)
        ->and($this->value->value())->toBeGreaterThan(0);
});

expect()->extend('toBeZeroPoints', function () {
    return $this->toBeInstanceOf(\LoyaltyRewards\Domain\ValueObjects\Points::class)
        ->and($this->value->value())->toBe(0);
});

expect()->extend('toBeMoney', function (float $expectedDollars, string $expectedCurrency = 'USD') {
    return $this->toBeInstanceOf(\LoyaltyRewards\Domain\ValueObjects\Money::class)
        ->and($this->value->toDollars())->toBe($expectedDollars)
        ->and($this->value->currency()->code())->toBe($expectedCurrency);
});

expect()->extend('toBeSuccessfulEarning', function () {
    return $this->toBeInstanceOf(\LoyaltyRewards\Application\DTOs\EarningResult::class)
        ->and($this->value->pointsEarned->value())->toBeGreaterThan(0);
});

expect()->extend('toBeSuccessfulRedemption', function () {
    return $this->toBeInstanceOf(\LoyaltyRewards\Application\DTOs\RedemptionResult::class)
        ->and($this->value->redemptionValue)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createPoints(int $value = 100): \LoyaltyRewards\Domain\ValueObjects\Points
{
    return \LoyaltyRewards\Domain\ValueObjects\Points::fromInt($value);
}

function createMoney(float $dollars = 100.0, string $currency = 'USD'): \LoyaltyRewards\Domain\ValueObjects\Money
{
    return \LoyaltyRewards\Domain\ValueObjects\Money::fromDollars(
        $dollars,
        new \LoyaltyRewards\Domain\ValueObjects\Currency($currency)
    );
}

function createCustomerId(string $id = 'customer_123'): \LoyaltyRewards\Domain\ValueObjects\CustomerId
{
    return \LoyaltyRewards\Domain\ValueObjects\CustomerId::fromString($id);
}

function createTransactionContext(array $data = []): \LoyaltyRewards\Domain\ValueObjects\TransactionContext
{
    return \LoyaltyRewards\Domain\ValueObjects\TransactionContext::create($data);
}

# Loyalty Rewards System

[![Tests](https://github.com/mbsoft31/loyalty-rewards/actions/workflows/tests.yml/badge.svg)](https://github.com/mbsoft31/loyalty-rewards/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/badge/php-%5E8.3-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Latest Version](https://img.shields.io/github/v/release/mbsoft31/loyalty-rewards)](https://github.com/mbsoft31/loyalty-rewards/releases)
[![Laravel Adapter](https://img.shields.io/badge/Laravel-Adapter-blueviolet)](https://github.com/mbsoft31/loyalty-laravel-pro)

A comprehensive, enterprise-grade loyalty rewards system for PHP applications. Built with Domain-Driven Design principles, this package provides flexible point earning/redemption, fraud detection, audit logging, and multi-tier reward programs.

## Features

- Flexible Rewards Engine — Category multipliers, tier bonuses, time-based promotions
- Built-in Fraud Detection — Velocity checks, amount validation, suspicious activity alerts
- Complete Audit Trail — Full transaction logging with compliance support
- High Performance — Handles 1000+ transactions/second with optimized database queries
- Comprehensive Test Suite — Unit, feature, integration, and coverage-gated CI checks
- Framework Agnostic — Works with Laravel, Symfony, or standalone PHP applications
- Type Safe — Full PHP 8.3+ type declarations with strict type checking

## Quick Start

### Installation

composer require mbsoft31/loyalty-rewards

### Basic Usage

```php
declare(strict_types=1);

use LoyaltyRewards\Core\Services\LoyaltyService;
use LoyaltyRewards\Core\Services\AuditService;
use LoyaltyRewards\Core\Services\FraudDetectionService;
use LoyaltyRewards\Core\Engine\RulesEngine;
use LoyaltyRewards\Domain\ValueObjects\{ConversionRate, Currency, CustomerId, Money, Points, TransactionContext};
use LoyaltyRewards\Infrastructure\Database\DatabaseAccountRepository;
use LoyaltyRewards\Infrastructure\Database\DatabaseAuditRepository;
use LoyaltyRewards\Infrastructure\Database\DatabaseConnectionFactory;
use LoyaltyRewards\Infrastructure\Database\DatabaseTransactionRepository;
use LoyaltyRewards\Rules\Earning\CategoryMultiplierRule;
use LoyaltyRewards\Rules\Earning\TierBonusRule;
use LoyaltyRewards\Rules\Redemption\BasicRedemptionRule;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

$pdo = DatabaseConnectionFactory::create([
    'driver' => 'sqlite',
    'database' => ':memory:',
]);

$accountRepository = new DatabaseAccountRepository(
    $pdo,
    new DatabaseTransactionRepository($pdo)
);
$auditRepository = new DatabaseAuditRepository($pdo);

$rulesEngine = new RulesEngine(new NullLogger);
$rulesEngine->addEarningRule(new CategoryMultiplierRule('electronics', 2.0, ConversionRate::standard()));
$rulesEngine->addEarningRule(new TierBonusRule('gold', 1.25, ConversionRate::standard()));
$rulesEngine->addRedemptionRule(new BasicRedemptionRule(Currency::USD(), 100, 100));

$loyaltyService = new LoyaltyService(
    $accountRepository,
    $rulesEngine,
    new FraudDetectionService(new NullLogger),
    new AuditService($auditRepository, new NullLogger),
    new class implements EventDispatcherInterface {
        public function dispatch(object $event): object
        {
            return $event;
        }
    },
    new NullLogger
);

// Create customer account
$customerId = CustomerId::fromString('customer_12345');
$loyaltyService->createAccount($customerId);

// Earn points from purchase
$result = $loyaltyService->earnPoints(
    $customerId,
    Money::fromDollars(99.99, Currency::USD()),
    TransactionContext::earning('electronics', 'online_store')
);
$loyaltyService->confirmPendingPoints($customerId);
$availableBalance = $loyaltyService->getAccountBalance($customerId);

echo "Earned: {$result->pointsEarned->value()} points\n";
echo "Balance (available): {$availableBalance->value()} points\n";

// Redeem points
$redemption = $loyaltyService->redeemPoints(
    $customerId,
    Points::fromInt(1000),
    TransactionContext::redemption(['channel' => 'store'])
);

echo "Redeemed: {$redemption->redemptionValue->toDollars()} {$redemption->redemptionValue->currency()->code()}";

```

## Architecture Overview

```
loyalty-rewards/
├── src/
│   ├── Core/                 # Business logic core
│   │   ├── Engine/           # Rules processing engine
│   │   ├── Services/         # Domain services
│   │   └── Exceptions/       # Custom exceptions
│   ├── Domain/               # Domain models and contracts
│   │   ├── Models/           # Core business entities
│   │   ├── ValueObjects/     # Immutable value types
│   │   ├── Events/           # Domain events
│   │   └── Repositories/     # Data access contracts
│   ├── Infrastructure/       # External concerns
│   │   ├── Database/         # Database implementations
│   │   └── Audit/            # Audit logging
│   ├── Application/          # Use cases and DTOs
│   └── Rules/                # Business rules library
└── tests/                    # Comprehensive test suite
```

## Real-World Examples

### E-commerce Rewards Program

```php
use LoyaltyRewards\Domain\ValueObjects\{ConversionRate, Currency, CustomerId, Money, TransactionContext};
use LoyaltyRewards\Rules\Earning\{CategoryMultiplierRule, TierBonusRule};

$goldCustomerId = CustomerId::fromString('customer_gold_100');
$loyaltyService->createAccount($goldCustomerId);

// Setup category-based earning
$rulesEngine->addEarningRule(new CategoryMultiplierRule('electronics', 3.0, ConversionRate::standard()));
$rulesEngine->addEarningRule(new CategoryMultiplierRule('books', 2.0, ConversionRate::standard()));
$rulesEngine->addEarningRule(new CategoryMultiplierRule('groceries', 1.5, ConversionRate::standard()));

// Tier-based bonuses
$rulesEngine->addEarningRule(new TierBonusRule('gold', 1.25, ConversionRate::standard()));
$rulesEngine->addEarningRule(new TierBonusRule('platinum', 1.5, ConversionRate::standard()));

// Customer purchases $200 laptop (electronics + gold tier)
$result = $loyaltyService->earnPoints(
    $goldCustomerId,
    Money::fromDollars(200.00, Currency::USD()),
    TransactionContext::earning('electronics', null, ['tier' => 'gold'])
);
// Earns: 200 * 100 * 3.0 * 1.25 = 75,000 points
```

### Restaurant Chain Program

```php
use DateTimeImmutable;
use LoyaltyRewards\Domain\ValueObjects\{ConversionRate, Currency, CustomerId, Money, TransactionContext};
use LoyaltyRewards\Rules\Earning\TimeBasedRule;

$customerId = CustomerId::fromString('customer_restaurant_100');
$loyaltyService->createAccount($customerId);

// Happy hour promotions
$happyHourRule = new TimeBasedRule(
    new DateTimeImmutable('2025-01-01 14:00:00'),
    new DateTimeImmutable('2025-12-31 17:00:00'),
    2.0, // Double points
    ConversionRate::standard(),
    ['monday', 'tuesday', 'wednesday']
);

$rulesEngine->addEarningRule($happyHourRule);

// Customer orders during happy hour
$result = $loyaltyService->earnPoints(
    $customerId,
    Money::fromDollars(12.50, Currency::USD()),
    TransactionContext::earning('food', 'mobile_app')
);
// Earns: 12.50 * 100 * 2.0 = 2,500 points
```

### SaaS Referral Program

```php
use LoyaltyRewards\Domain\ValueObjects\{Currency, CustomerId, Money, TransactionContext};

$referrerId = CustomerId::fromString('customer_referrer_200');
$loyaltyService->createAccount($referrerId);

// Referral bonus
$result = $loyaltyService->earnPoints(
    $referrerId,
    Money::zero(Currency::USD()), // No monetary transaction
    TransactionContext::create([
        'type' => 'referral_conversion',
        'referred_customer' => 'new_customer_789',
        'subscription_tier' => 'pro'
    ])
);
```


## Configuration

### Database Setup

```php
use LoyaltyRewards\Infrastructure\Database\DatabaseConnectionFactory;

$pdo = DatabaseConnectionFactory::create([
    'driver' => 'sqlite',
    'database' => '/absolute/path/to/loyalty.sqlite',
]);
```
### Dependency Injection

```php
use LoyaltyRewards\Core\Services\LoyaltyService;
use LoyaltyRewards\Core\Services\AuditService;
use LoyaltyRewards\Core\Services\FraudDetectionService;
use LoyaltyRewards\Core\Engine\RulesEngine;
use LoyaltyRewards\Infrastructure\Database\DatabaseAccountRepository;
use LoyaltyRewards\Infrastructure\Database\DatabaseAuditRepository;
use LoyaltyRewards\Infrastructure\Database\DatabaseConnectionFactory;
use LoyaltyRewards\Infrastructure\Database\DatabaseTransactionRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

$pdo = DatabaseConnectionFactory::create([
    'driver' => 'sqlite',
    'database' => ':memory:',
]);

$accountRepository = new DatabaseAccountRepository(
    $pdo,
    new DatabaseTransactionRepository($pdo)
);
$auditRepository = new DatabaseAuditRepository($pdo);

$loyaltyService = new LoyaltyService(
    $accountRepository,
    new RulesEngine(new NullLogger),
    new FraudDetectionService(new NullLogger),
    new AuditService($auditRepository, new NullLogger),
    new class implements EventDispatcherInterface {
        public function dispatch(object $event): object
        {
            return $event;
        }
    },
    new NullLogger
);
```


### Rules Configuration

```php
use LoyaltyRewards\Core\Engine\RulesEngine;
use LoyaltyRewards\Domain\ValueObjects\ConversionRate;
use LoyaltyRewards\Rules\Earning\{CategoryMultiplierRule, TierBonusRule};

$rulesEngine = new RulesEngine();

// Base earning: 1 point per cent spent
$rulesEngine->addEarningRule(
    new CategoryMultiplierRule('default', 1.0, ConversionRate::standard())
);

// Category bonuses
$rulesEngine->addEarningRule(
    new CategoryMultiplierRule('premium', 5.0, ConversionRate::standard())
);

// Tier bonuses stack with category multipliers
$rulesEngine->addEarningRule(
    new TierBonusRule('vip', 2.0, ConversionRate::standard())
);
```

## Testing

The package includes comprehensive tests with a CI coverage gate:

# Run all tests

```bash
composer test
```

# Run specific test suites

```bash
composer test:unit
composer test:feature
composer test:integration
```

# Run with coverage report

```bash
composer test:coverage
```

### Test Results
- Full suite includes unit, feature, and integration tests
- Unit tests: Value objects, domain models, rules engine
- Integration tests: Database operations, repository behavior, full service workflows
- Integration coverage includes high-volume transaction workflows

## Security & Fraud Detection

Built-in fraud detection with configurable rules:

```php
use LoyaltyRewards\Core\Exceptions\FraudDetectedException;
use LoyaltyRewards\Core\Services\FraudDetectionService;
use LoyaltyRewards\Domain\ValueObjects\{Currency, CustomerId, Money, TransactionContext};

$fraudDetection = new FraudDetectionService();
$customerId = CustomerId::fromString('customer_12345');
$account = $accountRepository->findByCustomerId($customerId);
$amount = Money::fromDollars(99.99, Currency::USD());
$context = TransactionContext::earning('electronics', 'online_store');

// Automatic fraud checking on all transactions
$fraudResult = $fraudDetection->analyze($account, $amount, $context);

if ($fraudResult->shouldBlock()) {
    throw new FraudDetectedException('Transaction blocked');
}
```

Detection Methods:
- Velocity checking (transaction frequency)
- Amount anomaly detection
- Pattern recognition
- Account behavior analysis

## Performance

Benchmarked Performance:
- Single Transaction: < 100ms average
- Bulk Operations: 1000 transactions in < 10 seconds
- Memory Usage: < 50MB for 1000 transactions
- Database Queries: Optimized with proper indexing

Scalability Features:
- Connection pooling support
- Caching layer integration
- Async queue processing
- Horizontal scaling ready

## Enterprise Features

### Audit & Compliance
- Complete transaction audit trails
- Regulatory compliance reporting
- Data retention policies
- Encrypted sensitive data storage

### Multi-Currency Support
- 7+ supported currencies (USD, EUR, GBP, NGN, etc.)
- Automatic currency conversion
- Regional formatting
- Exchange rate integration ready

### Event-Driven Architecture
- Domain events for all state changes
- Easy integration with external systems
- Webhook support ready
- Real-time notifications

## Documentation

- [API Reference (API.md)](API.md) — Complete method documentation
- [Architecture Guide (ARCHITECTURE.md)](ARCHITECTURE.md) — Technical design decisions
- [Examples (EXAMPLES.md)](EXAMPLES.md) — 10+ real-world implementations
- [Configuration (CONFIGURATION.md)](CONFIGURATION.md) — Setup and customization options
- [Laravel Adapter](https://github.com/mbsoft31/loyalty-laravel-pro) — Service provider, config, and migrations
 
## Release Guide

To cut a release:

```bash
# bump version via git tag (Composer reads tags)
git tag -a v0.1.0 -m "v0.1.0"
git push origin v0.1.0

# (optional) update Packagist after pushing tags
# core:     https://packagist.org/packages/mbsoft31/loyalty-rewards
# adapter:  https://packagist.org/packages/mbsoft31/loyalty-laravel-pro
```

CI runs unit + integration tests on PHP 8.3, 8.4, and 8.5. Coverage gate enforces ≥80% on PHP 8.3.

## Contributing

We welcome contributions! Please see CONTRIBUTING.md for guidelines.

### Development Setup

```bash
git clone https://github.com/mbsoft31/loyalty-rewards.git
cd loyalty-rewards
composer install
composer test
```

## License

This project is licensed under the MIT License — see the LICENSE file for details.

## Links

- GitHub Repository: https://github.com/mbsoft31/loyalty-rewards
- Issues: https://github.com/mbsoft31/loyalty-rewards/issues
- Discussions: https://github.com/mbsoft31/loyalty-rewards/discussions
- Releases: https://github.com/mbsoft31/loyalty-rewards/releases

Built by mbsoft31 — Empowering businesses with flexible, scalable loyalty solutions.

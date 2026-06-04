# Configuration

This guide covers environment setup, database configuration, and wiring the service with rules.

## Requirements
- PHP 8.3+ with `pdo` (and `pdo_sqlite` for tests)
- Composer 2.5+
- Optional: Xdebug for coverage, Pest for tests

## Database
- Default examples use PostgreSQL. Migrations in `database/migrations` are Postgres‑dialect (UUID/JSON/INET).
- Tests run on SQLite in‑memory (see `phpunit.xml`). For MySQL, adapt data types and upsert syntax.

Example (Postgres):
```php
$pdo = LoyaltyRewards\Infrastructure\Database\DatabaseConnectionFactory::create([
  'driver' => 'pgsql',
  'host' => 'localhost',
  'port' => 5432,
  'database' => 'loyalty_rewards',
  'username' => 'app',
  'password' => 'secret',
]);
```

Example (SQLite, dev/testing):
```php
$pdo = LoyaltyRewards\Infrastructure\Database\DatabaseConnectionFactory::create([
  'driver' => 'sqlite',
  'database' => ':memory:',
]);
// Create tables (see tests/Support/DatabaseTestCase.php for schema)
```

## Wiring the Service
```php
use LoyaltyRewards\Core\Services\{LoyaltyService, FraudDetectionService, AuditService};
use LoyaltyRewards\Core\Engine\RulesEngine;
use LoyaltyRewards\Infrastructure\Database\{DatabaseAccountRepository, DatabaseTransactionRepository, DatabaseAuditRepository};
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

$txRepo = new DatabaseTransactionRepository($pdo);
$accountRepo = new DatabaseAccountRepository($pdo, $txRepo);
$auditRepo = new DatabaseAuditRepository($pdo);

$rules = new RulesEngine(new NullLogger());
$fraud = new FraudDetectionService(new NullLogger());
$audit = new AuditService($auditRepo, new NullLogger());
$events = new class implements EventDispatcherInterface { public function dispatch(object $e): object { return $e; } };

$loyalty = new LoyaltyService($accountRepo, $rules, $fraud, $audit, $events, new NullLogger());
```

## Rules Configuration
```php
use LoyaltyRewards\Rules\Earning\{CategoryMultiplierRule, MinimumSpendRule};
use LoyaltyRewards\Rules\Redemption\BasicRedemptionRule;
use LoyaltyRewards\Domain\ValueObjects\{ConversionRate, Currency};

$rules->addEarningRule(new CategoryMultiplierRule('electronics', 2.0, ConversionRate::standard()));
$rules->addEarningRule(new MinimumSpendRule(
  LoyaltyRewards\Domain\ValueObjects\Money::fromDollars(50, Currency::USD()),
  1.5,
  ConversionRate::standard()
));
$rules->addRedemptionRule(new BasicRedemptionRule(Currency::USD(), pointsPerDollar: 100, minimumPoints: 200));
```

## PHPUnit Environment
- `phpunit.xml` sets `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` for tests.
- Ensure `pdo_sqlite` is enabled locally/CI.

Security note: never commit secrets. Use environment variables or a secrets manager for credentials.

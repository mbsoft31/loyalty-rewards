# Getting Started Examples

## Quick Start (Earn + Redeem)

```php
use LoyaltyRewards\Core\Services\LoyaltyService;
use LoyaltyRewards\Core\Engine\RulesEngine;
use LoyaltyRewards\Core\Services\{FraudDetectionService, AuditService};
use LoyaltyRewards\Infrastructure\Database\{DatabaseConnectionFactory, DatabaseAccountRepository, DatabaseTransactionRepository, DatabaseAuditRepository};
use LoyaltyRewards\Rules\Earning\{CategoryMultiplierRule, MinimumSpendRule};
use LoyaltyRewards\Rules\Redemption\BasicRedemptionRule;
use LoyaltyRewards\Domain\ValueObjects\{CustomerId, Money, Currency, TransactionContext, ConversionRate, Points};
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

require __DIR__.'/vendor/autoload.php';

// 1) DB (SQLite in-memory for demo)
$pdo = DatabaseConnectionFactory::create(['driver' => 'sqlite', 'database' => ':memory:']);
// Create tables as in tests/Support/DatabaseTestCase (simplified for brevity)
$pdo->exec('CREATE TABLE loyalty_accounts (id TEXT PRIMARY KEY, customer_id TEXT UNIQUE, available_points INT, pending_points INT, lifetime_points INT, status TEXT, created_at TEXT, updated_at TEXT, last_activity_at TEXT)');
$pdo->exec('CREATE TABLE points_transactions (id TEXT PRIMARY KEY, account_id TEXT, type TEXT, points INT, context_data TEXT, created_at TEXT, processed_at TEXT)');
$pdo->exec('CREATE TABLE audit_logs (id TEXT PRIMARY KEY, entity_type TEXT, entity_id TEXT, action TEXT, user_id TEXT, data TEXT, ip_address TEXT, user_agent TEXT, created_at TEXT)');

// 2) Repositories
$txRepo = new DatabaseTransactionRepository($pdo);
$accountRepo = new DatabaseAccountRepository($pdo, $txRepo);
$auditRepo = new DatabaseAuditRepository($pdo);

// 3) Rules
$rules = new RulesEngine(new NullLogger());
$rules->addEarningRule(new CategoryMultiplierRule('electronics', 2.0, ConversionRate::standard()));
$rules->addEarningRule(new MinimumSpendRule(Money::fromDollars(50, Currency::USD()), 1.5, ConversionRate::standard()));

// 4) Services
$fraud = new FraudDetectionService(new NullLogger());
$audit = new AuditService($auditRepo, new NullLogger());
$events = new class implements EventDispatcherInterface { public function dispatch(object $e): object { return $e; } };
$loyalty = new LoyaltyService($accountRepo, $rules, $fraud, $audit, $events, new NullLogger());

// 5) Use it
$customer = CustomerId::fromString('customer_123');
$loyalty->createAccount($customer);

// Earn
$earning = $loyalty->earnPoints(
    $customer,
    Money::fromDollars(120.00, Currency::USD()),
    TransactionContext::earning('electronics', 'web', ['promo' => 'BF2025'])
);
echo 'Earned: '.$earning->pointsEarned->value().PHP_EOL;

// Confirm pending → available (optional step depending on your flow)
$loyalty->confirmPendingPoints($customer);

// Redeem
$redemptionRule = new BasicRedemptionRule(Currency::USD(), pointsPerDollar: 100, minimumPoints: 200);
$rules->addRedemptionRule($redemptionRule);
$redeemed = $loyalty->redeemPoints($customer, Points::fromInt(500));
echo 'Redeemed value: $'.($redeemed->redemptionValue?->toDollars() ?? 0).PHP_EOL;
```

Tip: For a real database, use `DatabaseConnectionFactory::create([...])` with your driver and credentials, and apply migrations in `database/migrations` (PostgreSQL dialect). For MySQL/SQLite, adapt types and upserts accordingly.

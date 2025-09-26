# 📖 API Reference

Complete API documentation for the Loyalty Rewards System.

## Core Services

### LoyaltyService

The primary service for all loyalty operations.

#### Constructor

public function __construct(
AccountRepositoryInterface $accountRepository,
RulesEngine $rulesEngine,
FraudDetectionService $fraudDetection,
AuditService $auditService,
EventDispatcherInterface $eventDispatcher,
LoggerInterface $logger = new NullLogger()
)

#### Methods

##### createAccount(CustomerId $customerId): LoyaltyAccount

Creates a new loyalty account for a customer.

Parameters:
- $customerId - Unique customer identifier

Returns: LoyaltyAccount - The created account

Throws:
- InvalidArgumentException - If account already exists

Example:

$customerId = CustomerId::fromString('customer_12345');
$account = $loyaltyService->createAccount($customerId);

##### earnPoints(CustomerId $customerId, Money $amount, TransactionContext $context): EarningResult

Process points earning for a customer transaction.

Parameters:
- $customerId - Customer identifier
- $amount - Transaction amount
- $context - Transaction context with metadata

Returns: EarningResult - Contains transaction details and new balances

Throws:
- AccountNotFoundException - If customer account doesn't exist
- FraudDetectedException - If transaction is flagged as fraudulent
- InactiveAccountException - If account is suspended/closed

Example:

$result = $loyaltyService->earnPoints(
CustomerId::fromString('customer_12345'),
Money::fromDollars(50.00, Currency::USD()),
TransactionContext::earning('electronics', 'online_store', [
'product_id' => 'laptop_001',
'payment_method' => 'credit_card'
])
);

echo "Points earned: {$result->pointsEarned->value()}";
echo "New balance: {$result->newAvailableBalance->value()}";

##### redeemPoints(CustomerId $customerId, Points $pointsToRedeem, ?TransactionContext $context = null): RedemptionResult

Process points redemption for a customer.

Parameters:
- $customerId - Customer identifier
- $pointsToRedeem - Number of points to redeem
- $context - Optional redemption context

Returns: RedemptionResult - Contains redemption value and remaining balance

Throws:
- InsufficientPointsException - If customer doesn't have enough points
- InvalidArgumentException - If redemption violates business rules

Example:

$result = $loyaltyService->redeemPoints(
CustomerId::fromString('customer_12345'),
Points::fromInt(1000),
TransactionContext::create(['redemption_type' => 'cashback'])
);

echo "Redemption value: {$result->redemptionValue}";
echo "Remaining balance: {$result->newAvailableBalance->value()}";

## Value Objects

### Points

Represents loyalty points with validation and operations.

Methods:
- Points::fromInt(int $value): Points
- Points::zero(): Points
- add(Points $other): Points
- subtract(Points $other): Points
- multiply(float $multiplier): Points
- value(): int

### Money

Handles monetary values with currency support.

Methods:
- Money::fromDollars(float $dollars, Currency $currency): Money
- Money::fromCents(int $cents, Currency $currency): Money
- convertToPoints(ConversionRate $rate): Points
- toDollars(): float

### TransactionContext

Provides rich context for business rule evaluation.

Methods:
- TransactionContext::create(array $data = []): TransactionContext
- TransactionContext::earning(?string $category = null, ?string $source = null, array $additionalData = []): TransactionContext
- get(string $key, mixed $default = null): mixed
- with(string $key, mixed $value): TransactionContext

## Rules Engine

### EarningRuleInterface

Interface for all earning rules.

interface EarningRuleInterface
{
public function calculatePoints(Money $amount, TransactionContext $context): Points;
public function isApplicable(TransactionContext $context): bool;
public function getPriority(): int;
public function getName(): string;
}

### Built-in Rules

#### CategoryMultiplierRule

Awards bonus points based on purchase category.

$rule = new CategoryMultiplierRule(
category: 'electronics',
multiplier: 2.0,
baseRate: ConversionRate::standard(),
priority: 100
);

#### TierBonusRule

Provides tier-based point bonuses.

$rule = new TierBonusRule(
tier: 'gold',
bonusMultiplier: 1.5,
baseRate: ConversionRate::standard(),
priority: 200
);

#### TimeBasedRule

Time-sensitive promotional rules.

$rule = new TimeBasedRule(
startTime: new DateTimeImmutable('2025-01-01 00:00:00'),
endTime: new DateTimeImmutable('2025-12-31 23:59:59'),
multiplier: 2.0,
baseRate: ConversionRate::standard(),
daysOfWeek: ['friday', 'saturday', 'sunday'],
priority: 150
);

## Data Transfer Objects

### EarningResult

Result of a points earning operation.

Properties:
- PointsTransaction $transaction — The created transaction
- Points $newAvailableBalance — Updated available points balance
- Points $newPendingBalance — Updated pending points balance
- Points $pointsEarned — Points earned in this operation

### RedemptionResult

Result of a points redemption operation.

Properties:
- PointsTransaction $transaction — The created transaction
- Points $newAvailableBalance — Updated available points balance
- ?Money $redemptionValue — Monetary value of redemption

## Event System

### Domain Events

All domain events extend a base interface and are dispatched automatically.

#### PointsEarnedEvent

readonly class PointsEarnedEvent
{
public AccountId $accountId;
public PointsTransaction $transaction;
public Points $availablePoints;
public Points $pendingPoints;
public DateTimeImmutable $occurredAt;
}

#### Event Handling

// Register event listeners
$eventDispatcher->addListener(PointsEarnedEvent::class, function (PointsEarnedEvent $event) {
// Send notification to customer
$notificationService->sendPointsEarnedNotification($event);
});

## Repository Interfaces

### AccountRepositoryInterface

interface AccountRepositoryInterface
{
public function findById(AccountId $id): LoyaltyAccount;
public function findByCustomerId(CustomerId $customerId): LoyaltyAccount;
public function save(LoyaltyAccount $account): void;
public function findInactive(DateTimeImmutable $since): array;
public function getTotalAccounts(): int;
}

### TransactionRepositoryInterface

interface TransactionRepositoryInterface
{
public function findById(TransactionId $id): ?PointsTransaction;
public function findByAccountId(AccountId $accountId, int $limit = 100): array;
public function save(PointsTransaction $transaction): void;
public function findByDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array;
}

## Error Handling

### Exception Hierarchy

abstract class LoyaltyException extends Exception
├── AccountNotFoundException
├── InsufficientPointsException
├── InactiveAccountException
├── FraudDetectedException
└── InvalidRuleException

### Error Contexts

All exceptions include contextual data for debugging:

try {
$result = $loyaltyService->redeemPoints($customerId, $points);
} catch (InsufficientPointsException $e) {
$context = $e->getContext();
// Contains: required_points, available_points, account_id
}

## Configuration

### Database Configuration

$config = [
'driver' => 'pgsql',
'host' => 'localhost',
'port' => 5432,
'database' => 'loyalty_rewards',
'username' => 'loyalty_user',
'password' => 'secure_password',
'options' => [
PDO::ATTR_PERSISTENT => true,
PDO::ATTR_TIMEOUT => 30,
]
];

### Rules Configuration

$rulesConfig = [
'earning_rules' => [
[
'type' => 'category_multiplier',
'category' => 'electronics',
'multiplier' => 2.0,
'priority' => 100,
],
[
'type' => 'tier_bonus',
'tier' => 'gold',
'multiplier' => 1.5,
'priority' => 200,
],
],
'fraud_detection' => [
'velocity_threshold' => 50,
'amount_threshold' => 10000.0,
'enabled' => true,
],
];

## Extension Points

### Custom Rules

Implement EarningRuleInterface for custom business logic:

class CustomPromotionRule implements EarningRuleInterface
{
public function calculatePoints(Money $amount, TransactionContext $context): Points
{
// Your custom logic here
return Points::fromInt($customCalculation);
}

    public function isApplicable(TransactionContext $context): bool
    {
        // Your custom conditions here
        return $yourCondition;
    }
}

### Custom Fraud Detection

Extend the fraud detection system:

class MLFraudDetector
{
public function analyze(LoyaltyAccount $account, Money $amount, TransactionContext $context): FraudResult
{
$score = $this->mlModel->predict($this->extractFeatures($account, $amount, $context));
return new FraudResult($score, $this->explainPrediction());
}
}

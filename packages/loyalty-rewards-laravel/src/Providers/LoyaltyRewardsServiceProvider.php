<?php

declare(strict_types=1);

namespace LoyaltyRewards\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use LoyaltyRewards\Core\Engine\RulesEngine;
use LoyaltyRewards\Core\Services\AuditService;
use LoyaltyRewards\Core\Services\FraudDetectionService;
use LoyaltyRewards\Core\Services\LoyaltyService;
use LoyaltyRewards\Domain\Repositories\AccountRepositoryInterface;
use LoyaltyRewards\Domain\ValueObjects\ConversionRate;
use LoyaltyRewards\Domain\ValueObjects\Currency;
use LoyaltyRewards\Domain\ValueObjects\Money;
use LoyaltyRewards\Infrastructure\Database\DatabaseAccountRepository;
use LoyaltyRewards\Infrastructure\Database\DatabaseAuditRepository;
use LoyaltyRewards\Infrastructure\Database\DatabaseConnectionFactory;
use LoyaltyRewards\Infrastructure\Database\DatabaseTransactionRepository;
use LoyaltyRewards\Rules\Earning\CategoryMultiplierRule;
use LoyaltyRewards\Rules\Earning\MinimumSpendRule;
use LoyaltyRewards\Rules\Redemption\BasicRedemptionRule;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

final class LoyaltyRewardsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/loyalty-rewards.php', 'loyalty-rewards');

        // PDO connection for the package
        $this->app->singleton('loyalty-rewards.pdo', function () {
            $config = (array) config('loyalty-rewards.database', []);

            return DatabaseConnectionFactory::create($config);
        });

        // Infrastructure bindings
        $this->app->singleton(DatabaseTransactionRepository::class, function ($app) {
            return new DatabaseTransactionRepository($app->make('loyalty-rewards.pdo'));
        });

        $this->app->singleton(AccountRepositoryInterface::class, function ($app) {
            return new DatabaseAccountRepository(
                $app->make('loyalty-rewards.pdo'),
                $app->make(DatabaseTransactionRepository::class)
            );
        });

        $this->app->singleton(DatabaseAuditRepository::class, function ($app) {
            return new DatabaseAuditRepository($app->make('loyalty-rewards.pdo'));
        });

        // Core services
        $this->app->singleton(RulesEngine::class, fn () => new RulesEngine(new NullLogger));
        $this->app->singleton(FraudDetectionService::class, fn () => new FraudDetectionService(new NullLogger));
        $this->app->singleton(AuditService::class, function ($app) {
            return new AuditService($app->make(DatabaseAuditRepository::class), new NullLogger);
        });

        // PSR-14 dispatcher fallback (no-op) if not bound
        $this->app->singleton('loyalty-rewards.dispatcher', function ($app) {
            if ($app->bound(EventDispatcherInterface::class)) {
                return $app->make(EventDispatcherInterface::class);
            }

            return new class implements EventDispatcherInterface
            {
                public function dispatch(object $event): object
                {
                    return $event;
                }
            };
        });

        $this->app->singleton(LoyaltyService::class, function ($app) {
            return new LoyaltyService(
                $app->make(AccountRepositoryInterface::class),
                $app->make(RulesEngine::class),
                $app->make(FraudDetectionService::class),
                $app->make(AuditService::class),
                $app->make('loyalty-rewards.dispatcher'),
                new NullLogger
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'loyalty-rewards-migrations');
        $this->publishes([
            __DIR__.'/../../config/loyalty-rewards.php' => config_path('loyalty-rewards.php'),
        ], 'loyalty-rewards-config');

        // Optional: bootstrap rules from config
        $this->app->afterResolving(RulesEngine::class, function (RulesEngine $engine) {
            $rules = (array) config('loyalty-rewards.rules', []);

            // Earning rules
            foreach (($rules['earning'] ?? []) as $cfg) {
                $type = $cfg['type'] ?? '';
                if ($type === 'category_multiplier') {
                    $engine->addEarningRule(new CategoryMultiplierRule(
                        (string) ($cfg['category'] ?? 'default'),
                        (float) ($cfg['multiplier'] ?? 1.0),
                        ConversionRate::standard(),
                        (int) ($cfg['priority'] ?? 100)
                    ));
                } elseif ($type === 'minimum_spend') {
                    $currency = new Currency((string) ($cfg['currency'] ?? 'USD'));
                    $engine->addEarningRule(new MinimumSpendRule(
                        Money::fromDollars((float) ($cfg['minimum'] ?? 0), $currency),
                        (float) ($cfg['multiplier'] ?? 1.0),
                        ConversionRate::standard(),
                        (int) ($cfg['priority'] ?? 125)
                    ));
                }
            }

            // Redemption rules
            foreach (($rules['redemption'] ?? []) as $cfg) {
                $type = $cfg['type'] ?? '';
                if ($type === 'basic') {
                    $currency = new Currency((string) ($cfg['currency'] ?? 'USD'));
                    $engine->addRedemptionRule(new BasicRedemptionRule(
                        $currency,
                        (int) ($cfg['points_per_dollar'] ?? 100),
                        (int) ($cfg['min_points'] ?? 100)
                    ));
                }
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Engine;

use LoyaltyRewards\Rules\Composites\CompositeEarningRule;
use LoyaltyRewards\Rules\Contracts\{EarningRuleInterface, RedemptionRuleInterface};
use LoyaltyRewards\Domain\ValueObjects\{Points, Money, TransactionContext};
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RulesEngine
{
    private CompositeEarningRule $earningRules;
    /** @var RedemptionRuleInterface[] */
    private array $redemptionRules = [];

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
        $this->earningRules = new CompositeEarningRule();
    }

    public function addEarningRule(EarningRuleInterface $rule): void
    {
        $this->earningRules->addRule($rule);

        $this->logger->info('Added earning rule', [
            'rule_name' => $rule->getName(),
            'priority' => $rule->getPriority(),
        ]);
    }

    public function removeEarningRule(string $ruleName): void
    {
        $this->earningRules->removeRule($ruleName);

        $this->logger->info('Removed earning rule', [
            'rule_name' => $ruleName,
        ]);
    }

    public function addRedemptionRule(RedemptionRuleInterface $rule): void
    {
        $this->redemptionRules[] = $rule;

        $this->logger->info('Added redemption rule', [
            'rule_name' => $rule->getName(),
        ]);
    }

    public function calculateEarning(Money $amount, TransactionContext $context): Points
    {
        $startTime = microtime(true);

        $points = $this->earningRules->calculatePoints($amount, $context);
        $applicableRules = $this->earningRules->getApplicableRules($context);

        $executionTime = microtime(true) - $startTime;

        $this->logger->info('Points calculated', [
            'amount' => $amount->toDollars(),
            'currency' => $amount->currency()->code(),
            'points_earned' => $points->value(),
            'applicable_rules' => array_map(fn($rule) => $rule->getName(), $applicableRules),
            'execution_time_ms' => round($executionTime * 1000, 2),
        ]);

        return $points;
    }

    public function calculateRedemption(Points $points, TransactionContext $context): ?Money
    {
        foreach ($this->redemptionRules as $rule) {
            if ($rule->canRedeem($points, $context)) {
                $value = $rule->calculateRedemptionValue($points, $context);

                $this->logger->info('Redemption calculated', [
                    'points_redeemed' => $points->value(),
                    'redemption_value' => $value->toDollars(),
                    'currency' => $value->currency()->code(),
                    'rule_used' => $rule->getName(),
                ]);

                return $value;
            }
        }

        $this->logger->warning('No redemption rules applicable', [
            'points' => $points->value(),
            'context' => $context->toArray(),
        ]);

        return null;
    }

    public function canRedeem(Points $points, TransactionContext $context): bool
    {
        foreach ($this->redemptionRules as $rule) {
            if ($rule->canRedeem($points, $context)) {
                return true;
            }
        }

        return false;
    }

    public function getEarningRules(): array
    {
        return $this->earningRules->getRules();
    }

    public function getRedemptionRules(): array
    {
        return $this->redemptionRules;
    }

    public function getApplicableEarningRules(TransactionContext $context): array
    {
        return $this->earningRules->getApplicableRules($context);
    }
}

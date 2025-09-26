<?php

declare(strict_types=1);

namespace LoyaltyRewards\Rules\Composites;

use LoyaltyRewards\Rules\Contracts\EarningRuleInterface;
use LoyaltyRewards\Domain\ValueObjects\{Points, Money, TransactionContext};

class CompositeEarningRule implements EarningRuleInterface
{
    /** @var EarningRuleInterface[] */
    private array $rules = [];

    public function __construct(array $rules = [])
    {
        foreach ($rules as $rule) {
            $this->addRule($rule);
        }
    }

    public function addRule(EarningRuleInterface $rule): void
    {
        $this->rules[] = $rule;

        // Sort by priority (higher priority first)
        usort($this->rules, fn($a, $b) => $b->getPriority() <=> $a->getPriority());
    }

    public function removeRule(string $ruleName): void
    {
        $this->rules = array_filter(
            $this->rules,
            fn($rule) => $rule->getName() !== $ruleName
        );
    }

    public function calculatePoints(Money $amount, TransactionContext $context): Points
    {
        $totalPoints = Points::zero();
        $contextWithAmount = $context->with('amount', $amount);

        foreach ($this->rules as $rule) {
            if ($rule->isApplicable($contextWithAmount)) {
                $rulePoints = $rule->calculatePoints($amount, $contextWithAmount);
                $totalPoints = $totalPoints->add($rulePoints);
            }
        }

        return $totalPoints;
    }

    public function isApplicable(TransactionContext $context): bool
    {
        // Composite rule is applicable if any sub-rule is applicable
        foreach ($this->rules as $rule) {
            if ($rule->isApplicable($context)) {
                return true;
            }
        }

        return false;
    }

    public function getPriority(): int
    {
        return 0; // Composite rules don't have individual priorities
    }

    public function getName(): string
    {
        return 'composite_earning_rule';
    }

    public function getDescription(): string
    {
        return 'Composite rule containing multiple earning rules';
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function getApplicableRules(TransactionContext $context): array
    {
        return array_filter(
            $this->rules,
            fn($rule) => $rule->isApplicable($context)
        );
    }
}

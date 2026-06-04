<?php

declare(strict_types=1);

namespace LoyaltyRewards\Premium\Rules\Earning;

use LoyaltyRewards\Domain\ValueObjects\ConversionRate;
use LoyaltyRewards\Domain\ValueObjects\Money;
use LoyaltyRewards\Domain\ValueObjects\Points;
use LoyaltyRewards\Domain\ValueObjects\TransactionContext;
use LoyaltyRewards\Rules\Contracts\EarningRuleInterface;
use LoyaltyRewards\Rules\Earning\BaseEarningRule;

final class AdvancedTierRule extends BaseEarningRule implements EarningRuleInterface
{
    /** @var array<string,float> */
    private array $tierMultipliers;

    private ConversionRate $baseRate;

    /**
     * @param  array<string,float>  $tierMultipliers
     */
    public function __construct(array $tierMultipliers, ?ConversionRate $baseRate = null, int $priority = 250)
    {
        $this->tierMultipliers = $tierMultipliers;
        $this->baseRate = $baseRate ?? ConversionRate::standard();

        parent::__construct(
            'advanced_tier_rule',
            'Apply multipliers based on customer tier',
            $priority
        );
    }

    public function calculatePoints(Money $amount, TransactionContext $context): Points
    {
        $tier = (string) $context->get('tier', '');
        $multiplier = $this->tierMultipliers[$tier] ?? 1.0;
        $basePoints = $amount->convertToPoints($this->baseRate);

        return $basePoints->multiply($multiplier);
    }

    public function isApplicable(TransactionContext $context): bool
    {
        return isset($this->tierMultipliers[(string) $context->get('tier', '')]);
    }
}

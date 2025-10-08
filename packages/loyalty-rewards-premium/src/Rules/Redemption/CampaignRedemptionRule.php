<?php

declare(strict_types=1);

namespace LoyaltyRewards\Premium\Rules\Redemption;

use LoyaltyRewards\Domain\ValueObjects\{Currency, Money, Points, TransactionContext};
use LoyaltyRewards\Rules\Contracts\RedemptionRuleInterface;

final class CampaignRedemptionRule implements RedemptionRuleInterface
{
    private Currency $currency;
    /** @var array<string,int> */
    private array $campaignPointsPerDollar;
    private int $defaultPointsPerDollar;
    private int $minimumPoints;

    /**
     * @param array<string,int> $campaignPointsPerDollar
     */
    public function __construct(
        Currency $currency,
        array $campaignPointsPerDollar = [],
        int $defaultPointsPerDollar = 100,
        int $minimumPoints = 100
    ) {
        $this->currency = $currency;
        $this->campaignPointsPerDollar = $campaignPointsPerDollar;
        $this->defaultPointsPerDollar = $defaultPointsPerDollar;
        $this->minimumPoints = $minimumPoints;
    }

    public function calculateRedemptionValue(Points $points, TransactionContext $context): Money
    {
        $campaign = (string) $context->get('campaign', '');
        $ppd = $this->campaignPointsPerDollar[$campaign] ?? $this->defaultPointsPerDollar;
        $dollars = $points->value() / $ppd;
        return Money::fromDollars($dollars, $this->currency);
    }

    public function canRedeem(Points $points, TransactionContext $context): bool
    {
        return $points->isGreaterThanOrEqual(Points::fromInt($this->minimumPoints));
    }

    public function getMinimumPoints(): Points
    {
        return Points::fromInt($this->minimumPoints);
    }

    public function getName(): string
    {
        return 'campaign_redemption_rule';
    }
}

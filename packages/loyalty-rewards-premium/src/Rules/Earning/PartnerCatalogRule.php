<?php

declare(strict_types=1);

namespace LoyaltyRewards\Premium\Rules\Earning;

use LoyaltyRewards\Domain\ValueObjects\ConversionRate;
use LoyaltyRewards\Domain\ValueObjects\Money;
use LoyaltyRewards\Domain\ValueObjects\Points;
use LoyaltyRewards\Domain\ValueObjects\TransactionContext;
use LoyaltyRewards\Rules\Contracts\EarningRuleInterface;
use LoyaltyRewards\Rules\Earning\BaseEarningRule;

final class PartnerCatalogRule extends BaseEarningRule implements EarningRuleInterface
{
    /** @var array<string,float> */
    private array $partnerMultipliers;

    private ConversionRate $baseRate;

    /**
     * @param  array<string,float>  $partnerMultipliers
     */
    public function __construct(array $partnerMultipliers, ?ConversionRate $baseRate = null, int $priority = 220)
    {
        $this->partnerMultipliers = $partnerMultipliers;
        $this->baseRate = $baseRate ?? ConversionRate::standard();

        parent::__construct(
            'partner_catalog_rule',
            'Apply partner-specific multipliers',
            $priority
        );
    }

    public function calculatePoints(Money $amount, TransactionContext $context): Points
    {
        $partner = (string) $context->get('partner', '');
        $multiplier = $this->partnerMultipliers[$partner] ?? 1.0;

        return $amount->convertToPoints($this->baseRate)->multiply($multiplier);
    }

    public function isApplicable(TransactionContext $context): bool
    {
        $partner = (string) $context->get('partner', '');

        return array_key_exists($partner, $this->partnerMultipliers);
    }
}

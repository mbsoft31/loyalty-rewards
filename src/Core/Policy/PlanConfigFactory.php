<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Policy;

final class PlanConfigFactory
{
    /**
     * @param  array<string, array<string, mixed>>  $plans
     */
    public static function fromArray(array $plans, string $activePlan): PlanCatalog
    {
        $definitions = [];

        foreach ($plans as $key => $config) {
            $definitions[(string) $key] = new PlanDefinition((string) $key, (array) $config);
        }

        return new PlanCatalog($definitions, $activePlan);
    }
}

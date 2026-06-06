<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Policy;

use InvalidArgumentException;

final readonly class PlanCatalog
{
    /**
     * @param  array<string, PlanDefinition>  $plans
     */
    public function __construct(
        private array $plans,
        private string $activePlan,
    ) {}

    /**
     * @return array<string, PlanDefinition>
     */
    public function all(): array
    {
        return $this->plans;
    }

    public function activeKey(): string
    {
        return $this->activePlan;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->plans);
    }

    public function active(): PlanDefinition
    {
        return $this->get($this->activePlan);
    }

    public function get(?string $key = null): PlanDefinition
    {
        $key ??= $this->activePlan;

        if (! $this->has($key)) {
            throw new InvalidArgumentException("Unknown loyalty plan [{$key}].");
        }

        return $this->plans[$key];
    }
}

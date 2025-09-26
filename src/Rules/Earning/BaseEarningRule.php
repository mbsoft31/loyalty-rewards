<?php

declare(strict_types=1);

namespace LoyaltyRewards\Rules\Earning;

use LoyaltyRewards\Rules\Contracts\EarningRuleInterface;

abstract class BaseEarningRule implements EarningRuleInterface
{
    public function __construct(
        protected readonly string $name,
        protected readonly string $description = '',
        protected readonly int $priority = 100
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}

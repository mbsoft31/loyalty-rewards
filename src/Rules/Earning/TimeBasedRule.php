<?php

declare(strict_types=1);

namespace LoyaltyRewards\Rules\Earning;

use LoyaltyRewards\Domain\ValueObjects\{Points, Money, TransactionContext, ConversionRate};
use DateTimeImmutable;

class TimeBasedRule extends BaseEarningRule
{
    public function __construct(
        private readonly DateTimeImmutable $startTime,
        private readonly DateTimeImmutable $endTime,
        private readonly float $multiplier,
        private readonly ConversionRate $baseRate,
        private readonly array $daysOfWeek = [], // Empty means all days
        int $priority = 150
    ) {
        $formatStart = $startTime->format('Y-m-d H:i');
        $formatEnd = $endTime->format('Y-m-d H:i');

        parent::__construct(
            "time_based_{$formatStart}_{$formatEnd}",
            "Earn {$multiplier}x points during promotional period",
            $priority
        );
    }

    public function calculatePoints(Money $amount, TransactionContext $context): Points
    {
        $basePoints = $amount->convertToPoints($this->baseRate);
        return $basePoints->multiply($this->multiplier);
    }

    public function isApplicable(TransactionContext $context): bool
    {
        $timestamp = $context->getTimestamp();

        // Check if within time range
        if ($timestamp < $this->startTime || $timestamp > $this->endTime) {
            return false;
        }

        // Check day of week if specified
        if (!empty($this->daysOfWeek)) {
            $dayOfWeek = strtolower($timestamp->format('l'));
            return in_array($dayOfWeek, $this->daysOfWeek);
        }

        return true;
    }

    public function getStartTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getEndTime(): DateTimeImmutable
    {
        return $this->endTime;
    }

    public function getMultiplier(): float
    {
        return $this->multiplier;
    }
}

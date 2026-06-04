<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Services\FraudDetection;

use JsonSerializable;

final readonly class FraudResult implements JsonSerializable
{
    /**
     * @param  list<string>  $reasons
     * @param  list<FraudResult>  $detectorResults
     */
    public function __construct(
        private float $score,
        private array $reasons = [],
        private array $detectorResults = []
    ) {}

    public function getScore(): float
    {
        return $this->score;
    }

    /**
     * @return list<string>
     */
    public function getReasons(): array
    {
        return $this->reasons;
    }

    /**
     * @return list<FraudResult>
     */
    public function getDetectorResults(): array
    {
        return $this->detectorResults;
    }

    public function isSuspicious(): bool
    {
        return $this->score >= 0.5; // 50% threshold for suspicious
    }

    public function shouldBlock(): bool
    {
        return $this->score >= 0.8; // 80% threshold for blocking
    }

    public function getRiskLevel(): string
    {
        return match (true) {
            $this->score >= 0.8 => 'high',
            $this->score >= 0.5 => 'medium',
            $this->score >= 0.2 => 'low',
            default => 'negligible'
        };
    }

    /**
     * @return array{
     *     score: float,
     *     reasons: list<string>,
     *     risk_level: string,
     *     suspicious: bool,
     *     should_block: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'reasons' => $this->reasons,
            'risk_level' => $this->getRiskLevel(),
            'suspicious' => $this->isSuspicious(),
            'should_block' => $this->shouldBlock(),
        ];
    }

    /**
     * @return array{
     *     score: float,
     *     reasons: list<string>,
     *     risk_level: string,
     *     suspicious: bool,
     *     should_block: bool
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

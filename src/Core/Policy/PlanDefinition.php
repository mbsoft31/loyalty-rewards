<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Policy;

final readonly class PlanDefinition
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private string $key,
        private array $config,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    public function currency(): string
    {
        return (string) ($this->config['currency'] ?? 'USD');
    }

    /**
     * @return array<string, mixed>
     */
    public function points(): array
    {
        return (array) ($this->config['points'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function earningRules(): array
    {
        return $this->listOfArrays('earning_rules');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function redemptionRules(): array
    {
        return $this->listOfArrays('redemption_rules');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function tiers(): array
    {
        $tiers = (array) ($this->config['tiers'] ?? []);
        $normalized = [];

        foreach ($tiers as $key => $tier) {
            $normalized[(string) $key] = (array) $tier;
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rewards(): array
    {
        return $this->listOfArrays('rewards');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function missions(): array
    {
        return $this->listOfArrays('missions');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function badges(): array
    {
        return $this->listOfArrays('badges');
    }

    /**
     * @return array<string, mixed>
     */
    public function referrals(): array
    {
        return (array) ($this->config['referrals'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function abuseControls(): array
    {
        return (array) ($this->config['abuse_controls'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listOfArrays(string $key): array
    {
        $items = (array) ($this->config[$key] ?? []);
        $normalized = [];

        foreach ($items as $item) {
            $normalized[] = (array) $item;
        }

        return $normalized;
    }
}

<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Policy;

final readonly class CustomerLoyaltyState
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $customerId,
        public string $plan,
        public int $availablePoints,
        public int $pendingPoints,
        public int $lifetimePoints,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function make(
        string $customerId,
        string $plan,
        int $availablePoints,
        int $pendingPoints,
        int $lifetimePoints,
        array $metadata = [],
    ): self {
        return new self(
            customerId: $customerId,
            plan: $plan,
            availablePoints: $availablePoints,
            pendingPoints: $pendingPoints,
            lifetimePoints: $lifetimePoints,
            metadata: $metadata,
        );
    }
}

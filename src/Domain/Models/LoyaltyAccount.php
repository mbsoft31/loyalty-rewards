<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\Models;

use LoyaltyRewards\Domain\ValueObjects\CustomerId;
use LoyaltyRewards\Domain\ValueObjects\{Points, AccountId, TransactionContext};
use LoyaltyRewards\Domain\Enums\{TransactionType, AccountStatus};
use LoyaltyRewards\Domain\Events\{PointsEarnedEvent, PointsRedeemedEvent, AccountCreatedEvent, TierUpgradedEvent};
use LoyaltyRewards\Core\Exceptions\{InsufficientPointsException, InactiveAccountException};
use DateTimeImmutable;
use JsonSerializable;

class LoyaltyAccount implements JsonSerializable
{
    private array $events = [];

    public function __construct(
        private readonly AccountId $id,
        private readonly CustomerId $customerId,
        private Points $availablePoints,
        private Points $pendingPoints,
        private Points $lifetimePoints,
        private AccountStatus $status,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $lastActivityAt = null
    ) {}

    public static function create(CustomerId $customerId): self
    {
        $account = new self(
            AccountId::generate(),
            $customerId,
            Points::zero(),
            Points::zero(),
            Points::zero(),
            AccountStatus::ACTIVE,
            new DateTimeImmutable()
        );

        $account->recordEvent(new AccountCreatedEvent($account->id, $customerId));
        return $account;
    }

    public function earnPoints(Points $points, TransactionContext $context): PointsTransaction
    {
        $this->guardAgainstInactiveAccount();

        $transaction = PointsTransaction::create(
            $this->id,
            TransactionType::EARN,
            $points,
            $context
        );

        // Add to pending points first
        $this->pendingPoints = $this->pendingPoints->add($points);
        $this->lastActivityAt = new DateTimeImmutable();

        $this->recordEvent(new PointsEarnedEvent(
            $this->id,
            $transaction,
            $this->availablePoints,
            $this->pendingPoints
        ));

        return $transaction;
    }

    public function confirmPendingPoints(Points $pointsToConfirm = null): void
    {
        $pointsToConfirm = $pointsToConfirm ?? $this->pendingPoints;

        if ($pointsToConfirm->isGreaterThan($this->pendingPoints)) {
            throw new \InvalidArgumentException('Cannot confirm more points than pending');
        }

        $this->availablePoints = $this->availablePoints->add($pointsToConfirm);
        $this->lifetimePoints = $this->lifetimePoints->add($pointsToConfirm);
        $this->pendingPoints = $this->pendingPoints->subtract($pointsToConfirm);
    }

    public function redeemPoints(Points $pointsToRedeem, TransactionContext $context = null): PointsTransaction
    {
        $this->guardAgainstInactiveAccount();
        $this->guardAgainstInsufficientPoints($pointsToRedeem);

        $context = $context ?? TransactionContext::redemption();

        $transaction = PointsTransaction::create(
            $this->id,
            TransactionType::REDEEM,
            $pointsToRedeem,
            $context
        );

        $this->availablePoints = $this->availablePoints->subtract($pointsToRedeem);
        $this->lastActivityAt = new DateTimeImmutable();

        $this->recordEvent(new PointsRedeemedEvent(
            $this->id,
            $transaction,
            $this->availablePoints
        ));

        return $transaction;
    }

    public function adjustPoints(Points $adjustment, string $reason): PointsTransaction
    {
        $this->guardAgainstInactiveAccount();

        $context = TransactionContext::create([
            'reason' => $reason,
            'type' => 'adjustment'
        ]);

        $transaction = PointsTransaction::create(
            $this->id,
            TransactionType::ADJUSTMENT,
            $adjustment,
            $context
        );

        if ($adjustment->isGreaterThan(Points::zero())) {
            // Positive adjustment - add points
            $this->availablePoints = $this->availablePoints->add($adjustment);
            $this->lifetimePoints = $this->lifetimePoints->add($adjustment);
        } else {
            // Negative adjustment - subtract points (but don't go below zero)
            $absoluteAdjustment = Points::fromInt(abs($adjustment->value()));
            if ($this->availablePoints->isGreaterThanOrEqual($absoluteAdjustment)) {
                $this->availablePoints = $this->availablePoints->subtract($absoluteAdjustment);
            } else {
                $this->availablePoints = Points::zero();
            }
        }

        $this->lastActivityAt = new DateTimeImmutable();

        return $transaction;
    }

    public function expirePoints(Points $pointsToExpire): PointsTransaction
    {
        $this->guardAgainstInactiveAccount();

        $actualExpired = $this->availablePoints->isGreaterThanOrEqual($pointsToExpire)
            ? $pointsToExpire
            : $this->availablePoints;

        $context = TransactionContext::create([
            'type' => 'expiration',
            'expired_points' => $actualExpired->value()
        ]);

        $transaction = PointsTransaction::create(
            $this->id,
            TransactionType::EXPIRE,
            $actualExpired,
            $context
        );

        $this->availablePoints = $this->availablePoints->subtract($actualExpired);
        $this->lastActivityAt = new DateTimeImmutable();

        return $transaction;
    }

    public function suspend(): void
    {
        $this->status = AccountStatus::SUSPENDED;
    }

    public function activate(): void
    {
        $this->status = AccountStatus::ACTIVE;
    }

    public function close(): void
    {
        $this->status = AccountStatus::CLOSED;
        $this->availablePoints = Points::zero();
        $this->pendingPoints = Points::zero();
    }

    // Getters
    public function getId(): AccountId
    {
        return $this->id;
    }

    public function getCustomerId(): CustomerId
    {
        return $this->customerId;
    }

    public function getAvailablePoints(): Points
    {
        return $this->availablePoints;
    }

    public function getPendingPoints(): Points
    {
        return $this->pendingPoints;
    }

    public function getLifetimePoints(): Points
    {
        return $this->lifetimePoints;
    }

    public function getStatus(): AccountStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastActivityAt(): ?DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function canEarnPoints(): bool
    {
        return $this->status->canEarnPoints();
    }

    public function canRedeemPoints(): bool
    {
        return $this->status->canRedeemPoints() && $this->availablePoints->isGreaterThan(Points::zero());
    }

    // Event handling
    public function getEvents(): array
    {
        return $this->events;
    }

    public function clearEvents(): void
    {
        $this->events = [];
    }

    private function recordEvent(object $event): void
    {
        $this->events[] = $event;
    }

    // Guards
    private function guardAgainstInactiveAccount(): void
    {
        if (!$this->canEarnPoints()) {
            throw new InactiveAccountException(
                "Cannot perform operations on {$this->status->value} account"
            );
        }
    }

    private function guardAgainstInsufficientPoints(Points $required): void
    {
        if (!$this->availablePoints->isGreaterThanOrEqual($required)) {
            throw new InsufficientPointsException(
                "Insufficient points. Required: {$required->value()}, Available: {$this->availablePoints->value()}"
            );
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id->toString(),
            'customer_id' => $this->customerId->toString(),
            'available_points' => $this->availablePoints->value(),
            'pending_points' => $this->pendingPoints->value(),
            'lifetime_points' => $this->lifetimePoints->value(),
            'status' => $this->status->value,
            'created_at' => $this->createdAt->format('c'),
            'last_activity_at' => $this->lastActivityAt?->format('c'),
        ];
    }
}

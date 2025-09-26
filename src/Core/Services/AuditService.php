<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Services;

use LoyaltyRewards\Domain\Models\{LoyaltyAccount, PointsTransaction};
use LoyaltyRewards\Domain\Repositories\AuditRepositoryInterface;
use LoyaltyRewards\Domain\ValueObjects\{Money, Points};
use LoyaltyRewards\Infrastructure\Audit\AuditRecord;
use LoyaltyRewards\Core\Services\FraudDetection\FraudResult;
use LoyaltyRewards\Domain\ValueObjects\TransactionContext;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AuditService
{
    public function __construct(
        private readonly AuditRepositoryInterface $auditRepository,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {}

    public function logAccountCreated(LoyaltyAccount $account, ?string $userId = null): void
    {
        $record = AuditRecord::create(
            entityType: 'loyalty_account',
            entityId: $account->getId()->toString(),
            action: 'account_created',
            userId: $userId,
            data: [
                'customer_id' => $account->getCustomerId()->toString(),
                'initial_status' => $account->getStatus()->value,
                'created_at' => $account->getCreatedAt()->format('c'),
            ],
            ipAddress: $this->getCurrentIpAddress(),
            userAgent: $this->getCurrentUserAgent()
        );

        $this->auditRepository->store($record);

        $this->logger->info('Account creation audited', [
            'account_id' => $account->getId()->toString(),
            'customer_id' => $account->getCustomerId()->toString(),
        ]);
    }

    public function logPointsEarned(
        LoyaltyAccount $account,
        PointsTransaction $transaction,
        Money $amount,
        ?string $userId = null
    ): void {
        $record = AuditRecord::create(
            entityType: 'points_transaction',
            entityId: $transaction->getId()->toString(),
            action: 'points_earned',
            userId: $userId,
            data: [
                'account_id' => $account->getId()->toString(),
                'customer_id' => $account->getCustomerId()->toString(),
                'points_earned' => $transaction->getPoints()->value(),
                'transaction_amount' => $amount->toDollars(),
                'currency' => $amount->currency()->code(),
                'context' => $transaction->getContext()->toArray(),
                'balance_after' => $account->getAvailablePoints()->value(),
                'pending_points_after' => $account->getPendingPoints()->value(),
            ],
            ipAddress: $this->getCurrentIpAddress(),
            userAgent: $this->getCurrentUserAgent()
        );

        $this->auditRepository->store($record);

        $this->logger->info('Points earning audited', [
            'transaction_id' => $transaction->getId()->toString(),
            'points_earned' => $transaction->getPoints()->value(),
        ]);
    }

    public function logPointsRedeemed(
        LoyaltyAccount $account,
        PointsTransaction $transaction,
        ?Money $redemptionValue = null,
        ?string $userId = null
    ): void {
        $record = AuditRecord::create(
            entityType: 'points_transaction',
            entityId: $transaction->getId()->toString(),
            action: 'points_redeemed',
            userId: $userId,
            data: [
                'account_id' => $account->getId()->toString(),
                'customer_id' => $account->getCustomerId()->toString(),
                'points_redeemed' => $transaction->getPoints()->value(),
                'redemption_value' => $redemptionValue?->toDollars(),
                'redemption_currency' => $redemptionValue?->currency()->code(),
                'context' => $transaction->getContext()->toArray(),
                'balance_after' => $account->getAvailablePoints()->value(),
            ],
            ipAddress: $this->getCurrentIpAddress(),
            userAgent: $this->getCurrentUserAgent()
        );

        $this->auditRepository->store($record);

        $this->logger->info('Points redemption audited', [
            'transaction_id' => $transaction->getId()->toString(),
            'points_redeemed' => $transaction->getPoints()->value(),
        ]);
    }

    public function logPointsConfirmed(
        LoyaltyAccount $account,
        ?Points $confirmedPoints = null,
        ?string $userId = null
    ): void {
        $record = AuditRecord::create(
            entityType: 'loyalty_account',
            entityId: $account->getId()->toString(),
            action: 'points_confirmed',
            userId: $userId,
            data: [
                'customer_id' => $account->getCustomerId()->toString(),
                'points_confirmed' => $confirmedPoints?->value() ?? $account->getPendingPoints()->value(),
                'available_balance_after' => $account->getAvailablePoints()->value(),
                'pending_balance_after' => $account->getPendingPoints()->value(),
            ],
            ipAddress: $this->getCurrentIpAddress(),
            userAgent: $this->getCurrentUserAgent()
        );

        $this->auditRepository->store($record);
    }

    public function logFraudAttempt(
        LoyaltyAccount $account,
        Money $amount,
        TransactionContext $context,
        FraudResult $fraudResult,
        ?string $userId = null
    ): void {
        $record = AuditRecord::create(
            entityType: 'fraud_detection',
            entityId: $account->getId()->toString(),
            action: 'fraud_detected',
            userId: $userId,
            data: [
                'customer_id' => $account->getCustomerId()->toString(),
                'transaction_amount' => $amount->toDollars(),
                'currency' => $amount->currency()->code(),
                'fraud_score' => $fraudResult->getScore(),
                'fraud_reasons' => $fraudResult->getReasons(),
                'action_taken' => $fraudResult->shouldBlock() ? 'blocked' : 'flagged',
                'context' => $context->toArray(),
            ],
            ipAddress: $this->getCurrentIpAddress(),
            userAgent: $this->getCurrentUserAgent()
        );

        $this->auditRepository->store($record);

        $this->logger->warning('Fraud attempt audited', [
            'account_id' => $account->getId()->toString(),
            'fraud_score' => $fraudResult->getScore(),
            'action_taken' => $fraudResult->shouldBlock() ? 'blocked' : 'flagged',
        ]);
    }

    private function getCurrentIpAddress(): ?string
    {
        // In a web context, you'd get this from the request
        // For CLI/testing, return null
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    private function getCurrentUserAgent(): ?string
    {
        // In a web context, you'd get this from the request
        // For CLI/testing, return null
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
}

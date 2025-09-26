<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Services;

use LoyaltyRewards\Domain\Models\LoyaltyAccount;
use LoyaltyRewards\Domain\ValueObjects\{Points, Money, CustomerId, TransactionContext};
use LoyaltyRewards\Domain\Repositories\AccountRepositoryInterface;
use LoyaltyRewards\Core\Engine\RulesEngine;
use LoyaltyRewards\Core\Services\{FraudDetectionService, AuditService};
use LoyaltyRewards\Core\Exceptions\{AccountNotFoundException, FraudDetectedException};
use LoyaltyRewards\Application\DTOs\{EarningResult, RedemptionResult};
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class LoyaltyService
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly RulesEngine $rulesEngine,
        private readonly FraudDetectionService $fraudDetection,
        private readonly AuditService $auditService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {}

    /**
     * @throws AccountNotFoundException
     * @throws FraudDetectedException
     */
    public function earnPoints(
        CustomerId $customerId,
        Money $amount,
        TransactionContext $context
    ): EarningResult {
        $this->logger->info('Processing points earning', [
            'customer_id' => $customerId->toString(),
            'amount' => $amount->toDollars(),
            'currency' => $amount->currency()->code(),
        ]);

        // Load account
        $account = $this->getAccount($customerId);

        // Fraud detection
        $fraudResult = $this->fraudDetection->analyze($account, $amount, $context);
        if ($fraudResult->shouldBlock()) {
            $this->auditService->logFraudAttempt($account, $amount, $context, $fraudResult);
            throw new FraudDetectedException(
                'Transaction blocked due to fraud detection',
                context: $fraudResult->toArray()
            );
        }

        if ($fraudResult->isSuspicious()) {
            $this->logger->warning('Suspicious transaction detected but allowed', [
                'customer_id' => $customerId->toString(),
                'fraud_score' => $fraudResult->getScore(),
                'reasons' => $fraudResult->getReasons(),
            ]);
        }

        // Calculate points using rules engine
        $pointsToEarn = $this->rulesEngine->calculateEarning($amount, $context);

        // Process earning
        $transaction = $account->earnPoints($pointsToEarn, $context);

        // Save account
        $this->accountRepository->save($account);

        // Audit logging
        $this->auditService->logPointsEarned($account, $transaction, $amount);

        // Dispatch events
        $this->dispatchAccountEvents($account);

        $result = new EarningResult(
            $transaction,
            $account->getAvailablePoints(),
            $account->getPendingPoints(),
            $pointsToEarn
        );

        $this->logger->info('Points earning completed', [
            'customer_id' => $customerId->toString(),
            'points_earned' => $pointsToEarn->value(),
            'transaction_id' => $transaction->getId()->toString(),
        ]);

        return $result;
    }

    /**
     * @throws AccountNotFoundException
     */
    public function redeemPoints(
        CustomerId $customerId,
        Points $pointsToRedeem,
        ?TransactionContext $context = null
    ): RedemptionResult {
        $this->logger->info('Processing points redemption', [
            'customer_id' => $customerId->toString(),
            'points_to_redeem' => $pointsToRedeem->value(),
        ]);

        // Load account
        $account = $this->getAccount($customerId);

        // Default context for redemption
        $context = $context ?? TransactionContext::redemption();

        // Validate redemption using rules engine
        if (!$this->rulesEngine->canRedeem($pointsToRedeem, $context)) {
            throw new \InvalidArgumentException('Redemption not allowed by current rules');
        }

        // Calculate redemption value
        $redemptionValue = $this->rulesEngine->calculateRedemption($pointsToRedeem, $context);

        // Process redemption
        $transaction = $account->redeemPoints($pointsToRedeem, $context);

        // Save account
        $this->accountRepository->save($account);

        // Audit logging
        $this->auditService->logPointsRedeemed($account, $transaction, $redemptionValue);

        // Dispatch events
        $this->dispatchAccountEvents($account);

        $result = new RedemptionResult(
            $transaction,
            $account->getAvailablePoints(),
            $redemptionValue
        );

        $this->logger->info('Points redemption completed', [
            'customer_id' => $customerId->toString(),
            'points_redeemed' => $pointsToRedeem->value(),
            'redemption_value' => $redemptionValue?->toDollars(),
            'transaction_id' => $transaction->getId()->toString(),
        ]);

        return $result;
    }

    public function createAccount(CustomerId $customerId): LoyaltyAccount
    {
        $this->logger->info('Creating loyalty account', [
            'customer_id' => $customerId->toString(),
        ]);

        // Check if account already exists
        try {
            $existingAccount = $this->accountRepository->findByCustomerId($customerId);
            if ($existingAccount) {
                throw new \InvalidArgumentException('Account already exists for customer');
            }
        } catch (AccountNotFoundException) {
            // Expected - account doesn't exist yet
        }

        $account = LoyaltyAccount::create($customerId);
        $this->accountRepository->save($account);

        // Audit logging
        $this->auditService->logAccountCreated($account);

        // Dispatch events
        $this->dispatchAccountEvents($account);

        $this->logger->info('Loyalty account created', [
            'customer_id' => $customerId->toString(),
            'account_id' => $account->getId()->toString(),
        ]);

        return $account;
    }

    /**
     * @throws AccountNotFoundException
     */
    public function getAccountBalance(CustomerId $customerId): Points
    {
        $account = $this->getAccount($customerId);
        return $account->getAvailablePoints();
    }

    /**
     * @throws AccountNotFoundException
     */
    public function confirmPendingPoints(CustomerId $customerId, ?Points $pointsToConfirm = null): void
    {
        $account = $this->getAccount($customerId);
        $account->confirmPendingPoints($pointsToConfirm);
        $this->accountRepository->save($account);

        $this->auditService->logPointsConfirmed($account, $pointsToConfirm);
        $this->dispatchAccountEvents($account);
    }

    /**
     * @throws AccountNotFoundException
     */
    private function getAccount(CustomerId $customerId): LoyaltyAccount
    {
        try {
            return $this->accountRepository->findByCustomerId($customerId);
        } catch (AccountNotFoundException $e) {
            $this->logger->error('Account not found', [
                'customer_id' => $customerId->toString(),
            ]);
            throw $e;
        }
    }

    private function dispatchAccountEvents(LoyaltyAccount $account): void
    {
        foreach ($account->getEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
        $account->clearEvents();
    }
}

<?php

declare(strict_types=1);

namespace LoyaltyRewards\Infrastructure\Audit;

use DateTimeImmutable;
use JsonSerializable;

final readonly class AuditRecord implements JsonSerializable
{
    public function __construct(
        public string $entityType,
        public string $entityId,
        public string $action,
        public ?string $userId,
        public array $data,
        public ?string $ipAddress,
        public ?string $userAgent,
        public DateTimeImmutable $createdAt
    ) {}

    public static function create(
        string $entityType,
        string $entityId,
        string $action,
        ?string $userId = null,
        array $data = [],
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return new self(
            $entityType,
            $entityId,
            $action,
            $userId,
            $data,
            $ipAddress,
            $userAgent,
            new DateTimeImmutable()
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'action' => $this->action,
            'user_id' => $this->userId,
            'data' => $this->data,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'created_at' => $this->createdAt->format('c'),
        ];
    }
}

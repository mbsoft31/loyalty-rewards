<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Exceptions;

use Exception;

abstract class LoyaltyException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        protected array $context = []
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getContext(): array
    {
        return $this->context;
    }
}

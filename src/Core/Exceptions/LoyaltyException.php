<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Exceptions;

use Exception;
use Throwable;

abstract class LoyaltyException extends Exception
{
    /**
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param array $context
     */
    public function __construct(
        string          $message = '',
        int             $code = 0,
        ?Throwable      $previous = null,
        protected array $context = []
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }
}

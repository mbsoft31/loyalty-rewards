<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final readonly class Currency implements JsonSerializable
{
    private const SUPPORTED_CURRENCIES = [
        'USD' => ['symbol' => '$', 'name' => 'US Dollar', 'decimals' => 2],
        'EUR' => ['symbol' => '€', 'name' => 'Euro', 'decimals' => 2],
        'GBP' => ['symbol' => '£', 'name' => 'British Pound', 'decimals' => 2],
        'CAD' => ['symbol' => 'C$', 'name' => 'Canadian Dollar', 'decimals' => 2],
        'AUD' => ['symbol' => 'A$', 'name' => 'Australian Dollar', 'decimals' => 2],
        'JPY' => ['symbol' => '¥', 'name' => 'Japanese Yen', 'decimals' => 0],
        'NGN' => ['symbol' => '₦', 'name' => 'Nigerian Naira', 'decimals' => 2],
    ];

    public function __construct(private string $code)
    {
        $code = strtoupper(trim($code));

        if (strlen($code) !== 3) {
            throw new InvalidArgumentException('Currency code must be 3 characters');
        }

        if (!isset(self::SUPPORTED_CURRENCIES[$code])) {
            throw new InvalidArgumentException("Unsupported currency code: {$code}");
        }

        $this->code = $code;
    }

    public static function USD(): self
    {
        return new self('USD');
    }

    public static function EUR(): self
    {
        return new self('EUR');
    }

    public static function GBP(): self
    {
        return new self('GBP');
    }

    public static function NGN(): self
    {
        return new self('NGN');
    }

    public static function supportedCurrencies(): array
    {
        return array_keys(self::SUPPORTED_CURRENCIES);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return self::SUPPORTED_CURRENCIES[$this->code]['name'];
    }

    public function symbol(): string
    {
        return self::SUPPORTED_CURRENCIES[$this->code]['symbol'];
    }

    public function decimals(): int
    {
        return self::SUPPORTED_CURRENCIES[$this->code]['decimals'];
    }

    public function equals(Currency $other): bool
    {
        return $this->code === $other->code;
    }

    public function format(float $amount, bool $includeSymbol = true): string
    {
        $decimals = $this->decimals();
        $formatted = number_format($amount, $decimals);

        if (!$includeSymbol) {
            return $formatted;
        }

        return match ($this->code) {
            'USD', 'CAD', 'AUD' => $this->symbol() . $formatted,
            'EUR', 'GBP' => $this->symbol() . $formatted,
            'JPY' => $this->symbol() . $formatted,
            'NGN' => $this->symbol() . $formatted,
            default => $formatted . ' ' . $this->code,
        };
    }

    public function jsonSerialize(): string
    {
        return $this->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}

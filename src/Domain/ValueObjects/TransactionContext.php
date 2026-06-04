<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\ValueObjects;

use DateTimeImmutable;
use JsonSerializable;

final class TransactionContext implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly array $data = [],
        private ?DateTimeImmutable $timestamp = null
    ) {
        $this->timestamp = $timestamp ?? new DateTimeImmutable;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function create(array $data = []): self
    {
        return new self($data);
    }

    /**
     * @param  array<string, mixed>  $additionalData
     */
    public static function redemption(array $additionalData = []): self
    {
        return new self([
            'type' => 'redemption',
            ...$additionalData,
        ]);
    }

    /**
     * @param  array<string, mixed>  $additionalData
     */
    public static function earning(
        ?string $category = null,
        ?string $source = null,
        array $additionalData = []
    ): self {
        $data = [];

        if ($category !== null) {
            $data['category'] = $category;
        }

        if ($source !== null) {
            $data['source'] = $source;
        }

        return new self([...$data, ...$additionalData]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function getCategory(): ?string
    {
        return $this->get('category');
    }

    public function getSource(): ?string
    {
        return $this->get('source');
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function with(string $key, mixed $value): self
    {
        return new self([...$this->data, $key => $value], $this->timestamp);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function merge(array $data): self
    {
        return new self([...$this->data, ...$data], $this->timestamp);
    }

    /**
     * @return array{data: array<string, mixed>, timestamp: string}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'timestamp' => $this->timestamp->format('c'),
        ];
    }

    /**
     * @return array{data: array<string, mixed>, timestamp: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

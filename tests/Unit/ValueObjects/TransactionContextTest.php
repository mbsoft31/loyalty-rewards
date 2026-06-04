<?php

use LoyaltyRewards\Domain\ValueObjects\TransactionContext;

describe('TransactionContext', function () {
    it('provides defaults and accessors', function () {
        $ctx = TransactionContext::create(['category' => 'electronics', 'source' => 'web']);
        expect($ctx->getCategory())->toBe('electronics');
        expect($ctx->getSource())->toBe('web');
        expect($ctx->has('category'))->toBeTrue();
        expect($ctx->get('unknown', 'default'))->toBe('default');
        expect($ctx->getTimestamp())->toBeInstanceOf(DateTimeImmutable::class);
    });

    it('supports with and merge', function () {
        $ctx = TransactionContext::create(['a' => 1]);
        $ctx2 = $ctx->with('b', 2);
        $ctx3 = $ctx2->merge(['c' => 3]);
        expect($ctx->toArray()['data'])->toHaveKeys(['a']);
        expect($ctx2->toArray()['data'])->toHaveKeys(['a', 'b']);
        expect($ctx3->toArray()['data'])->toHaveKeys(['a', 'b', 'c']);
    });

    it('factory methods set default data', function () {
        $redemption = TransactionContext::redemption(['foo' => 'bar']);
        expect($redemption->get('type'))->toBe('redemption');
        expect($redemption->get('foo'))->toBe('bar');

        $earning = TransactionContext::earning('books', 'mobile', ['x' => 1]);
        expect($earning->getCategory())->toBe('books');
        expect($earning->getSource())->toBe('mobile');
        expect($earning->get('x'))->toBe(1);
    });
});

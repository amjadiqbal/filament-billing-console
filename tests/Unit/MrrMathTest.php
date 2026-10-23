<?php

declare(strict_types=1);

use AmjadIqbal\FilamentBillingConsole\Support\Mrr;
use AmjadIqbal\FilamentBillingConsole\Support\StripeGateway;

$price = fn (?int $amount, ?string $interval, int $count = 1, ?string $usage = 'licensed'): array => [
    'unit_amount' => $amount, 'currency' => 'usd', 'interval' => $interval, 'interval_count' => $count, 'usage_type' => $usage,
];

it('normalises every billing interval to a month', function () use ($price): void {
    expect(Mrr::monthly($price(4900, 'month'), 1))->toEqual(4900)
        ->and(Mrr::monthly($price(4900, 'month'), 3))->toEqual(14700)
        ->and(Mrr::monthly($price(12000, 'year'), 1))->toEqual(1000)
        ->and(Mrr::monthly($price(9000, 'month', 3), 1))->toEqual(3000)
        ->and(Mrr::monthly($price(1200, 'week'), 1))->toEqual(5200)
        ->and(Mrr::monthly($price(100, 'day'), 1))->toEqual(100 * 365 / 12);
});

it('does not count metered or tiered prices', function () use ($price): void {
    expect(Mrr::monthly($price(500, 'month', 1, 'metered'), 1))->toBeNull()
        ->and(Mrr::monthly($price(null, 'month'), 1))->toBeNull()
        ->and(Mrr::monthly($price(500, null), 1))->toBeNull();
});

it('hides key material in messages', function (): void {
    expect(StripeGateway::mask('Invalid API Key provided: sk_test_********************abcd'))->toBe('Invalid API Key provided: [key hidden]')
        ->and(StripeGateway::mask('rk_live_51Habc and whsec_123 and pk_test_x'))->toBe('[key hidden] and [key hidden] and [key hidden]')
        ->and(StripeGateway::mask('No such customer: cus_123'))->toBe('No such customer: cus_123');
});

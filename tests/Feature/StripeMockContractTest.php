<?php

declare(strict_types=1);

// Contract check against Stripe's official stripe-mock (github.com/stripe/stripe-mock), which serves responses built
// from Stripe's OpenAPI spec. It proves the console reads real response shapes. scripts/ci-local.sh starts it in Docker
// and sets STRIPE_MOCK_URL; without it these tests are skipped.

use AmjadIqbal\FilamentBillingConsole\BillingConsolePlugin;
use AmjadIqbal\FilamentBillingConsole\Support\StripeData;
use AmjadIqbal\FilamentBillingConsole\Support\StripeGateway;
use AmjadIqbal\FilamentBillingConsole\Support\SubscriptionSync;
use Filament\Facades\Filament;
use Laravel\Cashier\Cashier;
use Stripe\ApiRequestor;

beforeEach(function (): void {
    $url = getenv('STRIPE_MOCK_URL');

    if (! $url) {
        $this->markTestSkipped('STRIPE_MOCK_URL is not set (scripts/ci-local.sh starts stripe-mock).');
    }

    ApiRequestor::setHttpClient(null);
    $this->apiBase = Cashier::$apiBaseUrl;
    Cashier::$apiBaseUrl = $url;
    Filament::getPanel('admin')->getPlugin(BillingConsolePlugin::ID)->cacheFor(0);
});

afterEach(function (): void {
    if (isset($this->apiBase)) {
        Cashier::$apiBaseUrl = $this->apiBase;
    }
});

it('reads invoices, payments and prices in the shape Stripe returns', function (): void {
    $invoices = StripeData::invoices();
    $payments = StripeData::payments();
    $prices = StripeData::prices();

    expect(StripeGateway::lastError())->toBeNull()
        ->and($invoices)->not->toBeEmpty()
        ->and($invoices[0])->toHaveKeys(['id', 'number', 'status', 'currency', 'total', 'created', 'invoice_pdf'])
        ->and($invoices[0]['id'])->toStartWith('in_')
        ->and($payments)->not->toBeEmpty()
        ->and($payments[0]['id'])->toStartWith('ch_')
        ->and($payments[0]['amount'])->toBeInt()
        ->and($prices)->toBeArray()->not->toBeEmpty();
});

it('syncs a subscription from a real-shaped Stripe subscription', function (): void {
    // stripe-mock answers with a fixed customer id; give the local user that id so Cashier's handler finds them.
    $customer = (string) StripeGateway::client()->subscriptions->retrieve('sub_mock1')->customer;
    $user = $this->makeUser('Mock Customer', ['stripe_id' => $customer]);
    $subscription = $this->makeSubscription($user, 'sub_mock1', 'incomplete');

    SubscriptionSync::subscription($subscription);

    expect($subscription->refresh()->stripe_status)->toBeIn(['active', 'trialing', 'past_due', 'unpaid', 'incomplete', 'paused', 'canceled'])
        ->and($subscription->items()->count())->toBeGreaterThan(0);
});

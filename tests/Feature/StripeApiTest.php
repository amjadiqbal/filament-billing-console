<?php

declare(strict_types=1);

use AmjadIqbal\FilamentBillingConsole\BillingConsolePlugin;
use AmjadIqbal\FilamentBillingConsole\Pages\Invoices;
use AmjadIqbal\FilamentBillingConsole\Pages\Payments;
use AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource;
use AmjadIqbal\FilamentBillingConsole\Support\Mrr;
use AmjadIqbal\FilamentBillingConsole\Tests\Fixtures\FakeStripe;
use AmjadIqbal\FilamentBillingConsole\Tests\Fixtures\Stripe;
use AmjadIqbal\FilamentBillingConsole\Widgets\BillingOverview;
use AmjadIqbal\FilamentBillingConsole\Widgets\CustomerInvoicesTable;
use AmjadIqbal\FilamentBillingConsole\Widgets\CustomerPaymentsTable;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->actingAs($this->makeUser());
    $this->acme = $this->makeUser('Acme Inc', ['stripe_id' => 'cus_acme']);
});

function consolePlugin(): BillingConsolePlugin
{
    /** @var BillingConsolePlugin */
    return Filament::getPanel('admin')->getPlugin(BillingConsolePlugin::ID);
}

it('lists invoices from Stripe with PDF links and a link to the local customer', function (): void {
    FakeStripe::route('GET /v1/invoices', FakeStripe::list('/v1/invoices', [
        Stripe::invoice('in_0001'),
        Stripe::invoice('in_0002', ['status' => 'open', 'customer' => 'cus_unknown', 'customer_email' => 'x@other.test', 'total' => 990000]),
    ]));

    livewire(Invoices::class)
        ->assertOk()
        ->assertSee(['INV-0001', 'INV-0002', 'billing@acme.test', 'Paid', 'Open', '$49.00', '$9,900.00'])
        ->assertSee('https://pay.stripe.com/invoice/in_0001/pdf', escape: false)
        ->assertSee('https://dashboard.stripe.com/test/invoices/in_0001', escape: false)
        ->assertSee(CustomerResource::getUrl('view', ['record' => $this->acme]), escape: false);

    expect(FakeStripe::$requests[0]['params'])->toMatchArray(['limit' => 100]);
});

it('searches and filters invoices', function (): void {
    FakeStripe::route('GET /v1/invoices', FakeStripe::list('/v1/invoices', [
        Stripe::invoice('in_0001'),
        Stripe::invoice('in_0002', ['customer_email' => 'ops@globex.test', 'customer_name' => 'Globex']),
    ]));

    livewire(Invoices::class)
        ->searchTable('globex')
        ->assertSee('INV-0002')
        ->assertDontSee('INV-0001')
        ->searchTable(null)
        ->filterTable('status', 'open');

    expect(collect(FakeStripe::$requests)->pluck('params.status')->filter()->values()->all())->toBe(['open']);
});

it('lists payments with method, refunds and failures', function (): void {
    FakeStripe::route('GET /v1/charges', FakeStripe::list('/v1/charges', [
        Stripe::charge('ch_0001'),
        Stripe::charge('ch_0002', ['status' => 'failed', 'failure_message' => 'Your card was declined.']),
        Stripe::charge('ch_0003', ['refunded' => true, 'amount_refunded' => 4900]),
    ]));

    livewire(Payments::class)
        ->assertOk()
        ->assertSee(['Visa •••• 4242', 'Succeeded', 'Failed', 'Your card was declined.', 'Refunded'])
        ->assertSee('https://pay.stripe.com/receipts/ch_0001', escape: false)
        ->assertSee('https://dashboard.stripe.com/test/payments/pi_0001', escape: false)
        ->filterTable('status', 'failed')
        ->assertSee('Your card was declined.')
        ->assertDontSee('https://pay.stripe.com/receipts/ch_0001', escape: false);
});

it('scopes the customer tables to that customer', function (): void {
    FakeStripe::route('GET /v1/invoices', FakeStripe::list('/v1/invoices', [Stripe::invoice('in_0001')]));
    FakeStripe::route('GET /v1/charges', FakeStripe::list('/v1/charges', [Stripe::charge('ch_0001')]));

    livewire(CustomerInvoicesTable::class, ['record' => $this->acme])->assertOk()->assertSee('INV-0001');
    livewire(CustomerPaymentsTable::class, ['record' => $this->acme])->assertOk()->assertSee('Visa •••• 4242');

    expect(collect(FakeStripe::$requests)->pluck('params.customer')->unique()->values()->all())->toBe(['cus_acme']);
});

it('caches API reads', function (): void {
    FakeStripe::route('GET /v1/invoices', FakeStripe::list('/v1/invoices', [Stripe::invoice('in_0001')]));

    livewire(Invoices::class)->assertSee('INV-0001');
    livewire(Invoices::class)->assertSee('INV-0001');

    expect(FakeStripe::count('GET /v1/invoices'))->toBe(1);

    consolePlugin()->cacheFor(0);
    livewire(Invoices::class)->assertSee('INV-0001');

    expect(FakeStripe::count('GET /v1/invoices'))->toBe(2);
});

it('shows a masked message when Stripe rejects the key, and does not cache the failure', function (): void {
    FakeStripe::route('GET /v1/invoices', Stripe::error('invalid_request_error', 'Invalid API Key provided: sk_test_****0000'), 401);

    livewire(Invoices::class)
        ->assertOk()
        ->assertSee('Could not load from Stripe')
        ->assertSee('Stripe rejected the API key')
        ->assertDontSee('sk_test_');

    FakeStripe::route('GET /v1/invoices', FakeStripe::list('/v1/invoices', [Stripe::invoice('in_0001')]));

    livewire(Invoices::class)->assertSee('INV-0001');
});

it('explains rate limits', function (): void {
    FakeStripe::route('GET /v1/charges', Stripe::error('invalid_request_error', 'Too many requests'), 429);

    livewire(Payments::class)->assertOk()->assertSee('rate limit');
});

it('explains a missing secret key without calling Stripe', function (): void {
    config()->set('cashier.secret', null);

    livewire(Invoices::class)->assertOk()->assertSee('No Stripe secret key is set');

    expect(FakeStripe::$requests)->toBe([]);
});

it('summarises MRR, active, trialing and churn from Cashier tables and Stripe prices', function (): void {
    FakeStripe::route('GET /v1/prices', FakeStripe::list('/v1/prices', [
        Stripe::price('price_pro_monthly', 4900),
        Stripe::price('price_team_yearly', 120000, 'year'),
        Stripe::price('price_metered', 10, 'month', 1, 'usd', 'metered'),
        Stripe::price('price_eur', 3900, 'month', 1, 'eur'),
    ]));

    $globex = $this->makeUser('Globex', ['stripe_id' => 'cus_globex']);
    $this->makeSubscription($this->acme, 'sub_a1', 'active', 'price_pro_monthly', ['quantity' => 2]);
    $this->makeSubscription($globex, 'sub_g1', 'active', 'price_team_yearly');
    $this->makeSubscription($globex, 'sub_g2', 'past_due', 'price_pro_monthly');
    $this->makeSubscription($globex, 'sub_g3', 'trialing', 'price_pro_monthly');
    $this->makeSubscription($globex, 'sub_g4', 'active', 'price_metered');
    $this->makeSubscription($globex, 'sub_g5', 'active', 'price_eur');
    $this->makeSubscription($globex, 'sub_g6', 'canceled', 'price_pro_monthly', ['ends_at' => now()->subDays(3)]);
    $this->makeSubscription($globex, 'sub_g7', 'canceled', 'price_pro_monthly', ['ends_at' => now()->subDays(90)]);

    $s = Mrr::summary();

    // 2 x 49.00 + 1200.00 / 12 + 49.00 (past due) = 247.00; metered and EUR not counted.
    expect($s['mrr'])->toBe(24700)
        ->and($s['active'])->toBe(4)
        ->and($s['past_due'])->toBe(1)
        ->and($s['trialing'])->toBe(1)
        ->and($s['churned'])->toBe(1)
        ->and($s['churn_rate'])->toBe(round(1 / 6 * 100, 1))
        ->and($s['uncounted'])->toBe(2);

    livewire(BillingOverview::class)
        ->assertOk()
        ->assertSee(['MRR (USD)', '$247.00', '2 items not counted', '1 past due', '16.7%']);
});

it('reports MRR in the configured currency', function (): void {
    FakeStripe::route('GET /v1/prices', FakeStripe::list('/v1/prices', [Stripe::price('price_eur', 3900, 'month', 1, 'eur')]));
    $this->makeSubscription($this->acme, 'sub_a1', 'active', 'price_eur');

    consolePlugin()->currency('EUR');

    expect(Mrr::summary())->toMatchArray(['mrr' => 3900, 'currency' => 'eur', 'uncounted' => 0]);
});

it('shows counts without MRR when prices cannot be read', function (): void {
    FakeStripe::route('GET /v1/prices', Stripe::error('api_error', 'boom'), 500);
    $this->makeSubscription($this->acme, 'sub_a1');

    livewire(BillingOverview::class)->assertOk()->assertSee(['—', 'Stripe returned an error: boom']);
});

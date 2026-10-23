<?php

declare(strict_types=1);

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource;
use AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource\Pages\ListCustomers;
use AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource\Pages\ViewCustomer;
use AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource\SubscriptionsRelationManager;
use AmjadIqbal\FilamentBillingConsole\Resources\SubscriptionResource;
use AmjadIqbal\FilamentBillingConsole\Resources\SubscriptionResource\Pages\ListSubscriptions;
use AmjadIqbal\FilamentBillingConsole\Resources\SubscriptionResource\Pages\ViewSubscription;
use AmjadIqbal\FilamentBillingConsole\Tests\Fixtures\FakeStripe;
use AmjadIqbal\FilamentBillingConsole\Tests\Fixtures\Stripe;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->actingAs($this->makeUser());

    $this->acme = $this->makeUser('Acme Inc', ['stripe_id' => 'cus_acme', 'pm_type' => 'visa', 'pm_last_four' => '4242']);
    $this->globex = $this->makeUser('Globex Ltd', ['stripe_id' => 'cus_globex']);
    $this->noStripe = $this->makeUser('Not A Customer');

    $this->active = $this->makeSubscription($this->acme, 'sub_acme1');
    $this->trial = $this->makeSubscription($this->globex, 'sub_glob1', 'trialing', 'price_team_yearly', ['trial_ends_at' => now()->addDays(10)]);
    $this->pastDue = $this->makeSubscription($this->globex, 'sub_glob2', 'past_due');

    FakeStripe::route('GET /v1/prices', FakeStripe::list('/v1/prices', [Stripe::price('price_pro_monthly', 4900), Stripe::price('price_team_yearly', 99000, 'year')]));
    FakeStripe::route('GET /v1/invoices', FakeStripe::list('/v1/invoices', [Stripe::invoice('in_0001')]));
    FakeStripe::route('GET /v1/charges', FakeStripe::list('/v1/charges', [Stripe::charge('ch_0001')]));
});

it('lists only users that are Stripe customers', function (): void {
    livewire(ListCustomers::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->acme, $this->globex])
        ->assertCanNotSeeTableRecords([$this->noStripe])
        ->assertSee('Visa •••• 4242');
});

it('searches customers by name, email and Stripe ID', function (): void {
    livewire(ListCustomers::class)
        ->searchTable('globex.ltd@')
        ->assertCanSeeTableRecords([$this->globex])
        ->assertCanNotSeeTableRecords([$this->acme])
        ->searchTable('cus_acme')
        ->assertCanSeeTableRecords([$this->acme])
        ->assertCanNotSeeTableRecords([$this->globex]);
});

it('filters customers by an active subscription', function (): void {
    $lapsed = $this->makeUser('Lapsed Co', ['stripe_id' => 'cus_lapsed']);
    $this->makeSubscription($lapsed, 'sub_laps1', 'canceled', attributes: ['ends_at' => now()->subDays(40)]);

    livewire(ListCustomers::class)
        ->filterTable('subscribed', true)
        ->assertCanSeeTableRecords([$this->acme, $this->globex])
        ->assertCanNotSeeTableRecords([$lapsed]);
});

it('shows a customer with subscriptions, invoices and payments', function (): void {
    livewire(ViewCustomer::class, ['record' => $this->acme->getKey()])
        ->assertOk()
        ->assertSee('cus_acme')
        ->assertActionVisible('syncFromStripe')
        ->assertActionVisible('openInStripe');

    livewire(SubscriptionsRelationManager::class, ['ownerRecord' => $this->acme, 'pageClass' => ViewCustomer::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$this->active]);
});

it('lists subscriptions with status text and filters by status', function (): void {
    livewire(ListSubscriptions::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->active, $this->trial, $this->pastDue])
        ->assertSee(['Active', 'Trialing', 'Past due'])
        ->filterTable('stripe_status', ['past_due'])
        ->assertCanSeeTableRecords([$this->pastDue])
        ->assertCanNotSeeTableRecords([$this->active, $this->trial]);
});

it('searches subscriptions by customer email', function (): void {
    livewire(ListSubscriptions::class)
        ->searchTable('acme.inc@')
        ->assertCanSeeTableRecords([$this->active])
        ->assertCanNotSeeTableRecords([$this->trial, $this->pastDue]);
});

it('filters subscriptions that are cancelled but still in their grace period', function (): void {
    $this->active->forceFill(['ends_at' => now()->addDays(5)])->save();

    livewire(ListSubscriptions::class)
        ->filterTable('ending', true)
        ->assertCanSeeTableRecords([$this->active])
        ->assertCanNotSeeTableRecords([$this->trial, $this->pastDue]);
});

it('shows a subscription with its items and links to the customer', function (): void {
    livewire(ViewSubscription::class, ['record' => $this->active->getKey()])
        ->assertOk()
        ->assertSee(['sub_acme1', 'price_pro_monthly', 'prod_pro', 'Acme Inc'])
        ->assertSee(CustomerResource::getUrl('view', ['record' => $this->acme]), escape: false);
});

it('badges past-due and unpaid subscriptions in gray', function (): void {
    expect(SubscriptionResource::getNavigationBadge())->toBe('1')
        ->and(SubscriptionResource::getNavigationBadgeColor())->toBe('gray');
});

it('is read-only: no create, edit or delete', function (): void {
    expect(CustomerResource::canCreate())->toBeFalse()
        ->and(SubscriptionResource::canCreate())->toBeFalse()
        ->and(SubscriptionResource::canEdit($this->active))->toBeFalse()
        ->and(SubscriptionResource::canDelete($this->active))->toBeFalse()
        ->and(SubscriptionResource::can('delete', $this->active))->toBeFalse()
        ->and(array_keys(SubscriptionResource::getPages()))->toBe(['index', 'view'])
        ->and(array_keys(CustomerResource::getPages()))->toBe(['index', 'view']);
});

it('links to the Stripe dashboard in test or live mode to match the key', function (): void {
    expect(BillingConsole::dashboardUrl('customers/cus_acme'))->toBe('https://dashboard.stripe.com/test/customers/cus_acme');

    config()->set('cashier.secret', 'sk_live_fixture');

    expect(BillingConsole::dashboardUrl('customers/cus_acme'))->toBe('https://dashboard.stripe.com/customers/cus_acme');
});

it('never renders the secret key', function (): void {
    $this->get(SubscriptionResource::getUrl('index'))->assertOk()->assertDontSee('sk_test_fixture000000');
    $this->get(CustomerResource::getUrl('view', ['record' => $this->acme]))->assertOk()->assertDontSee('sk_test_fixture000000');
});

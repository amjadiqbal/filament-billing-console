<?php

declare(strict_types=1);

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use AmjadIqbal\FilamentBillingConsole\BillingConsolePlugin;
use AmjadIqbal\FilamentBillingConsole\Pages\Invoices;
use AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource;
use AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource\Pages\ViewCustomer;
use AmjadIqbal\FilamentBillingConsole\Resources\SubscriptionResource;
use AmjadIqbal\FilamentBillingConsole\Resources\SubscriptionResource\Pages\ListSubscriptions;
use AmjadIqbal\FilamentBillingConsole\Resources\SubscriptionResource\Pages\ViewSubscription;
use AmjadIqbal\FilamentBillingConsole\Tests\Fixtures\FakeStripe;
use AmjadIqbal\FilamentBillingConsole\Tests\Fixtures\Stripe;
use AmjadIqbal\FilamentBillingConsole\Tests\Fixtures\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Laravel\Cashier\Subscription;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->admin = $this->makeUser();
    $this->actingAs($this->admin);
    $this->acme = $this->makeUser('Acme Inc', ['stripe_id' => 'cus_acme']);
    $this->sub = $this->makeSubscription($this->acme, 'sub_acme1');
    FakeStripe::route('GET /v1/prices', FakeStripe::list('/v1/prices', []));
});

function billingPlugin(): BillingConsolePlugin
{
    /** @var BillingConsolePlugin */
    return Filament::getPanel('admin')->getPlugin(BillingConsolePlugin::ID);
}

it('syncs a subscription from Stripe with Cashier\'s own webhook code, and only reads from Stripe', function (): void {
    FakeStripe::route('GET /v1/subscriptions/sub_acme1', Stripe::subscription('sub_acme1', 'cus_acme', [
        'status' => 'past_due',
        'cancel_at' => 1762592000,
        'items' => ['object' => 'list', 'data' => [
            ['id' => 'si_acme1', 'object' => 'subscription_item', 'quantity' => 3, 'price' => ['id' => 'price_team', 'object' => 'price', 'product' => 'prod_team']],
        ]],
    ]));

    livewire(ViewSubscription::class, ['record' => $this->sub->getKey()])
        ->callAction('syncFromStripe')
        ->assertNotified('Synced from Stripe');

    $this->sub->refresh();

    expect($this->sub->stripe_status)->toBe('past_due')
        ->and($this->sub->stripe_price)->toBe('price_team')
        ->and($this->sub->quantity)->toBe(3)
        ->and($this->sub->ends_at?->timestamp)->toBe(1762592000)
        ->and($this->sub->items()->first()?->stripe_product)->toBe('prod_team')
        ->and(collect(FakeStripe::$requests)->pluck('method')->unique()->values()->all())->toBe(['GET']);
});

it('keeps an incomplete_expired subscription instead of deleting it', function (): void {
    FakeStripe::route('GET /v1/subscriptions/sub_acme1', Stripe::subscription('sub_acme1', 'cus_acme', ['status' => 'incomplete_expired']));

    livewire(ViewSubscription::class, ['record' => $this->sub->getKey()])->callAction('syncFromStripe');

    expect(Subscription::query()->find($this->sub->getKey())?->stripe_status)->toBe('incomplete_expired')
        ->and($this->sub->items()->count())->toBe(1);
});

it('syncs a customer\'s payment method and subscriptions', function (): void {
    // Cashier retrieves the customer with the default payment method expanded.
    FakeStripe::route('GET /v1/customers/cus_acme', ['id' => 'cus_acme', 'object' => 'customer', 'default_source' => null, 'invoice_settings' => [
        'default_payment_method' => ['id' => 'pm_1', 'object' => 'payment_method', 'customer' => 'cus_acme', 'type' => 'card', 'card' => ['brand' => 'mastercard', 'last4' => '4444']],
    ]]);
    FakeStripe::route('GET /v1/subscriptions/sub_acme1', Stripe::subscription('sub_acme1', 'cus_acme', ['status' => 'canceled', 'canceled_at' => 1761000000]));

    livewire(ViewCustomer::class, ['record' => $this->acme->getKey()])
        ->callAction('syncFromStripe')
        ->assertNotified('Synced from Stripe');

    expect($this->acme->refresh()->pm_last_four)->toBe('4444')
        ->and($this->acme->pm_type)->toBe('mastercard')
        ->and($this->sub->refresh()->stripe_status)->toBe('canceled');
});

it('reports a failed sync without changing the record', function (): void {
    FakeStripe::route('GET /v1/subscriptions/sub_acme1', Stripe::error('invalid_request_error', 'No such subscription: sub_acme1'), 404);

    livewire(ViewSubscription::class, ['record' => $this->sub->getKey()])
        ->callAction('syncFromStripe')
        ->assertNotified('Sync failed');

    expect($this->sub->refresh()->stripe_status)->toBe('active');
});

it('rate-limits syncs per user', function (): void {
    FakeStripe::route('GET /v1/subscriptions/sub_acme1', Stripe::subscription('sub_acme1', 'cus_acme'));
    billingPlugin()->syncsPerMinute(2);
    $this->freezeTime();

    $page = livewire(ViewSubscription::class, ['record' => $this->sub->getKey()]);
    $page->callAction('syncFromStripe');
    $page->callAction('syncFromStripe');
    $page->callAction('syncFromStripe')->assertNotified('Too many syncs. Try again in 60 seconds.');

    expect(FakeStripe::count('GET /v1/subscriptions/sub_acme1'))->toBe(2);
});

it('lets authorizeUsing() decide every ability', function (): void {
    $asked = [];
    billingPlugin()->authorizeUsing(function (string $ability, $record, $user) use (&$asked): bool {
        $asked[] = $ability;

        return $user?->email === 'admin@example.test' && $ability !== 'subscriptions.sync';
    });

    expect(SubscriptionResource::can('viewAny'))->toBeTrue()
        ->and(BillingConsole::can('subscriptions.sync', $this->sub))->toBeFalse();

    livewire(ViewSubscription::class, ['record' => $this->sub->getKey()])->assertActionHidden('syncFromStripe');

    $this->actingAs($this->acme);

    expect(SubscriptionResource::can('viewAny'))->toBeFalse()
        ->and(CustomerResource::can('view', $this->acme))->toBeFalse()
        ->and(Invoices::canAccess())->toBeFalse();

    $this->get(SubscriptionResource::getUrl('index'))->assertForbidden();
    $this->get(Invoices::getUrl())->assertForbidden();
    expect($asked)->toContain('subscriptions.viewAny', 'subscriptions.sync', 'customers.view', 'invoices.viewAny');
});

it('requires the viewBillingConsole gate when the app defines it', function (): void {
    Gate::define('viewBillingConsole', fn (User $user): bool => $user->email === 'admin@example.test');

    expect(SubscriptionResource::can('viewAny'))->toBeTrue();

    $this->actingAs($this->acme);

    expect(SubscriptionResource::can('viewAny'))->toBeFalse()
        ->and(BillingConsole::can('overview.view'))->toBeFalse();
    $this->get(CustomerResource::getUrl('index'))->assertForbidden();
});

it('respects a policy on the subscription model, with sync falling back to update', function (): void {
    Gate::policy(Subscription::class, SubscriptionPolicyFixture::class);

    expect(SubscriptionResource::can('viewAny'))->toBeTrue()
        ->and(SubscriptionResource::can('view', $this->sub))->toBeFalse()
        ->and(BillingConsole::can('subscriptions.sync', $this->sub))->toBeFalse();

    livewire(ListSubscriptions::class)->assertOk();
});

it('can turn parts of the console off', function (): void {
    billingPlugin()->invoices(false)->payments(false);

    expect(Invoices::canAccess())->toBeFalse();

    FakeStripe::route('GET /v1/invoices', FakeStripe::list('/v1/invoices', []));

    livewire(ViewCustomer::class, ['record' => $this->acme->getKey()])->assertOk();
    expect(FakeStripe::count('GET /v1/invoices'))->toBe(0);
});

class SubscriptionPolicyFixture
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return false;
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return false;
    }
}

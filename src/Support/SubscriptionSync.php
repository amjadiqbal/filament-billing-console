<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Support;

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Laravel\Cashier\Subscription;
use Stripe\Subscription as StripeSubscription;
use Throwable;

/**
 * Writes Stripe's copy of a subscription into the Cashier tables with Cashier's own webhook code
 * (customer.subscription.updated), so the result is exactly what a delivered webhook would have stored. It only
 * reads from Stripe. Unlike the webhook, an incomplete_expired subscription is kept (status updated), not deleted.
 */
class SubscriptionSync extends WebhookController
{
    public function __construct()
    {
        // No middleware: this is never routed.
    }

    public static function subscription(Subscription $subscription): void
    {
        $stripe = StripeGateway::client()->subscriptions->retrieve((string) $subscription->getAttribute('stripe_id'));
        $customer = is_string($stripe->customer) ? $stripe->customer : $stripe->customer->id;
        $owner = $subscription->owner()->first();

        // Cashier's handler looks the owner up by Stripe customer and silently does nothing when it doesn't match.
        if (! $owner || $owner->getAttribute('stripe_id') !== $customer) {
            throw new SyncException(__('filament-billing-console::billing-console.errors.customer_mismatch', ['customer' => $customer]));
        }

        if ($stripe->status === StripeSubscription::STATUS_INCOMPLETE_EXPIRED) {
            $subscription->forceFill(['stripe_status' => $stripe->status])->save();

            return;
        }

        (new self)->handleCustomerSubscriptionUpdated(['data' => ['object' => $stripe->toArray()]]);
    }

    /**
     * Refresh the customer's default payment method and every subscription of theirs the Cashier tables already have.
     * A payment method that can't be read doesn't stop the subscriptions from syncing; its error is returned.
     *
     * @return array{subscriptions: int, payment_method_error: ?string}
     */
    public static function customer(Model $customer): array
    {
        $paymentMethodError = null;

        if (method_exists($customer, 'updateDefaultPaymentMethodFromStripe')) {
            try {
                $customer->updateDefaultPaymentMethodFromStripe();
            } catch (Throwable $e) {
                report($e);
                $paymentMethodError = StripeGateway::describe($e);
            }
        }

        $subscriptions = BillingConsole::subscriptionModel()::query()
            ->where($customer->getForeignKey(), $customer->getKey())
            ->get();

        foreach ($subscriptions as $subscription) {
            static::subscription($subscription);
        }

        return ['subscriptions' => $subscriptions->count(), 'payment_method_error' => $paymentMethodError];
    }
}

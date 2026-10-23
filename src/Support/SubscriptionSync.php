<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Support;

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Laravel\Cashier\Subscription;
use Stripe\Subscription as StripeSubscription;

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

        if ($stripe->status === StripeSubscription::STATUS_INCOMPLETE_EXPIRED) {
            $subscription->forceFill(['stripe_status' => $stripe->status])->save();

            return;
        }

        (new self)->handleCustomerSubscriptionUpdated(['data' => ['object' => $stripe->toArray()]]);
    }

    /**
     * Refresh the customer's default payment method and every subscription of theirs the Cashier tables already have.
     *
     * @return int The number of subscriptions synced.
     */
    public static function customer(Model $customer): int
    {
        if (method_exists($customer, 'updateDefaultPaymentMethodFromStripe')) {
            $customer->updateDefaultPaymentMethodFromStripe();
        }

        $synced = 0;

        $subscriptions = BillingConsole::subscriptionModel()::query()
            ->where($customer->getForeignKey(), $customer->getKey())
            ->get();

        foreach ($subscriptions as $subscription) {
            static::subscription($subscription);
            $synced++;
        }

        return $synced;
    }
}

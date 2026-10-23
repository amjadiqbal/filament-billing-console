<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Tests\Fixtures;

/**
 * Recorded-shape Stripe objects (trimmed to the fields the console reads), as Stripe's API returns them.
 */
class Stripe
{
    /**
     * @return array<string, mixed>
     */
    public static function invoice(string $id, array $overrides = []): array
    {
        return array_replace([
            'id' => $id,
            'object' => 'invoice',
            'number' => 'INV-'.substr($id, -4),
            'customer' => 'cus_acme',
            'customer_email' => 'billing@acme.test',
            'customer_name' => 'Acme Inc',
            'status' => 'paid',
            'currency' => 'usd',
            'total' => 4900,
            'amount_due' => 4900,
            'amount_paid' => 4900,
            'created' => 1760000000,
            'due_date' => null,
            'invoice_pdf' => "https://pay.stripe.com/invoice/{$id}/pdf",
            'hosted_invoice_url' => "https://invoice.stripe.com/i/{$id}",
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public static function charge(string $id, array $overrides = []): array
    {
        return array_replace([
            'id' => $id,
            'object' => 'charge',
            'payment_intent' => 'pi_'.substr($id, 3),
            'customer' => 'cus_acme',
            'billing_details' => ['email' => 'billing@acme.test'],
            'receipt_email' => null,
            'description' => 'Subscription update',
            'status' => 'succeeded',
            'refunded' => false,
            'amount_refunded' => 0,
            'currency' => 'usd',
            'amount' => 4900,
            'payment_method_details' => ['type' => 'card', 'card' => ['brand' => 'visa', 'last4' => '4242']],
            'failure_message' => null,
            'created' => 1760000000,
            'receipt_url' => "https://pay.stripe.com/receipts/{$id}",
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public static function price(string $id, ?int $amount, string $interval = 'month', int $count = 1, string $currency = 'usd', string $usage = 'licensed'): array
    {
        return [
            'id' => $id,
            'object' => 'price',
            'currency' => $currency,
            'unit_amount' => $amount,
            'type' => 'recurring',
            'product' => 'prod_'.$id,
            'recurring' => ['interval' => $interval, 'interval_count' => $count, 'usage_type' => $usage],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function subscription(string $id, string $customer, array $overrides = []): array
    {
        return array_replace([
            'id' => $id,
            'object' => 'subscription',
            'customer' => $customer,
            'status' => 'active',
            'cancel_at_period_end' => false,
            'cancel_at' => null,
            'canceled_at' => null,
            'trial_end' => null,
            'metadata' => ['type' => 'default'],
            'items' => [
                'object' => 'list',
                'data' => [[
                    'id' => 'si_'.substr($id, 4),
                    'object' => 'subscription_item',
                    'quantity' => 1,
                    'current_period_end' => 1762592000,
                    'price' => ['id' => 'price_pro_monthly', 'object' => 'price', 'product' => 'prod_pro'],
                ]],
            ],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public static function error(string $type, string $message): array
    {
        return ['error' => ['type' => $type, 'message' => $message]];
    }
}

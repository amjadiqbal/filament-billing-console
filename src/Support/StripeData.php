<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Support;

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use Stripe\StripeClient;

/**
 * The Stripe reads the console makes, as plain arrays (cache-safe). All of them are list or retrieve calls.
 */
class StripeData
{
    /**
     * Latest invoices, optionally for one customer and one status.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function invoices(?string $customer = null, ?string $status = null): array
    {
        $params = array_filter([
            'limit' => BillingConsole::plugin()->getMaxListRecords(),
            'customer' => $customer,
            'status' => $status,
        ]);

        return StripeGateway::read('invoices:'.md5(serialize($params)), function (StripeClient $stripe) use ($params): array {
            return array_map(fn (array $invoice): array => [
                'id' => $invoice['id'],
                'number' => $invoice['number'] ?? null,
                'customer' => static::id($invoice['customer'] ?? null),
                'customer_email' => $invoice['customer_email'] ?? null,
                'customer_name' => $invoice['customer_name'] ?? null,
                'status' => $invoice['status'] ?? null,
                'currency' => $invoice['currency'] ?? null,
                'total' => $invoice['total'] ?? null,
                'amount_due' => $invoice['amount_due'] ?? null,
                'amount_paid' => $invoice['amount_paid'] ?? null,
                'created' => static::date($invoice['created'] ?? null),
                'due_date' => static::date($invoice['due_date'] ?? null),
                'invoice_pdf' => $invoice['invoice_pdf'] ?? null,
                'hosted_invoice_url' => $invoice['hosted_invoice_url'] ?? null,
            ], $stripe->invoices->all($params)->toArray()['data'] ?? []);
        }, []);
    }

    /**
     * Latest payments (charges), optionally for one customer.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function payments(?string $customer = null): array
    {
        $params = array_filter([
            'limit' => BillingConsole::plugin()->getMaxListRecords(),
            'customer' => $customer,
        ]);

        return StripeGateway::read('charges:'.md5(serialize($params)), function (StripeClient $stripe) use ($params): array {
            return array_map(function (array $charge): array {
                $details = $charge['payment_method_details'] ?? [];
                $card = $details['card'] ?? null;
                $refunded = (int) ($charge['amount_refunded'] ?? 0);
                $status = ($charge['refunded'] ?? false) ? 'refunded' : ($refunded > 0 ? 'partially_refunded' : ($charge['status'] ?? null));

                return [
                    'id' => $charge['id'],
                    'payment_intent' => static::id($charge['payment_intent'] ?? null),
                    'customer' => static::id($charge['customer'] ?? null),
                    'email' => $charge['billing_details']['email'] ?? $charge['receipt_email'] ?? null,
                    'description' => $charge['description'] ?? null,
                    'status' => $status,
                    'currency' => $charge['currency'] ?? null,
                    'amount' => $charge['amount'] ?? 0,
                    'amount_refunded' => $refunded,
                    'method' => $card ? trim(ucfirst((string) ($card['brand'] ?? '')).' •••• '.($card['last4'] ?? '')) : ($details['type'] ?? null),
                    'failure_message' => $charge['failure_message'] ?? null,
                    'created' => static::date($charge['created'] ?? null),
                    'receipt_url' => $charge['receipt_url'] ?? null,
                ];
            }, $stripe->charges->all($params)->toArray()['data'] ?? []);
        }, []);
    }

    /**
     * Recurring prices by id, for MRR: unit amount, currency, interval and interval count. Up to 1,000 prices.
     *
     * @return array<string, array{unit_amount: ?int, currency: string, interval: ?string, interval_count: int, usage_type: ?string}>|null
     */
    public static function prices(): ?array
    {
        return StripeGateway::read('prices', function (StripeClient $stripe): array {
            $prices = [];

            foreach ($stripe->prices->all(['limit' => 100, 'type' => 'recurring'])->autoPagingIterator() as $object) {
                $price = $object->toArray();
                $prices[$price['id']] = [
                    'unit_amount' => $price['unit_amount'] ?? null,
                    'currency' => (string) ($price['currency'] ?? ''),
                    'interval' => $price['recurring']['interval'] ?? null,
                    'interval_count' => (int) ($price['recurring']['interval_count'] ?? 1),
                    'usage_type' => $price['recurring']['usage_type'] ?? null,
                ];

                if (count($prices) >= 1000) {
                    break;
                }
            }

            return $prices;
        });
    }

    /**
     * An expandable field's id, whether Stripe returned the id or the expanded object.
     */
    protected static function id(mixed $value): ?string
    {
        return is_array($value) ? ($value['id'] ?? null) : (is_string($value) ? $value : null);
    }

    protected static function date(?int $timestamp): ?string
    {
        return $timestamp ? date(DATE_ATOM, $timestamp) : null;
    }
}

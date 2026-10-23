<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Support;

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use Illuminate\Support\Carbon;

/**
 * List-price MRR and churn from your Cashier tables. Prices come from Stripe (cached). Not counted: discounts, tax,
 * metered and tiered prices (no fixed unit amount), and prices in another currency than the report currency.
 */
class Mrr
{
    /**
     * Monthly amount of one price, in the smallest currency unit, or null when it has no fixed monthly value.
     *
     * @param  array{unit_amount: ?int, currency: string, interval: ?string, interval_count: int, usage_type: ?string}  $price
     */
    public static function monthly(array $price, int $quantity): ?float
    {
        if ($price['unit_amount'] === null || $price['usage_type'] === 'metered') {
            return null;
        }

        $count = max(1, $price['interval_count']);

        $perMonth = match ($price['interval']) {
            'day' => $price['unit_amount'] * 365 / 12 / $count,
            'week' => $price['unit_amount'] * 52 / 12 / $count,
            'month' => $price['unit_amount'] / $count,
            'year' => $price['unit_amount'] / 12 / $count,
            default => null,
        };

        return $perMonth === null ? null : $perMonth * max(1, $quantity);
    }

    /**
     * @return array{mrr: ?int, currency: string, active: int, trialing: int, past_due: int, churned: int, churn_rate: ?float, uncounted: int, error: ?string}
     */
    public static function summary(): array
    {
        $model = BillingConsole::subscriptionModel();
        $currency = BillingConsole::plugin()->getCurrency();
        $now = Carbon::now();
        $since = $now->copy()->subDays(30);

        $count = fn (array $statuses): int => $model::query()->whereIn('stripe_status', $statuses)->count();

        $active = $count(['active']);
        $pastDue = $count(['past_due']);
        $churned = $model::query()
            ->where('stripe_status', 'canceled')
            ->whereBetween('ends_at', [$since, $now])
            ->count();
        $base = $active + $pastDue + $churned;

        $summary = [
            'mrr' => null,
            'currency' => $currency,
            'active' => $active,
            'trialing' => $count(['trialing']),
            'past_due' => $pastDue,
            'churned' => $churned,
            'churn_rate' => $base > 0 ? round($churned / $base * 100, 1) : null,
            'uncounted' => 0,
            'error' => null,
        ];

        // MRR counts subscriptions that bill: active and past due (still owed), not trials.
        $items = $model::query()
            ->whereIn('stripe_status', ['active', 'past_due'])
            ->with('items')
            ->get()
            ->flatMap(fn ($subscription) => $subscription->items);

        if ($items->isEmpty()) {
            $summary['mrr'] = 0;

            return $summary;
        }

        StripeGateway::clearError();
        $prices = StripeData::prices();

        if ($prices === null) {
            $summary['error'] = StripeGateway::lastError();

            return $summary;
        }

        $total = 0.0;

        foreach ($items as $item) {
            $price = $prices[(string) $item->getAttribute('stripe_price')] ?? null;
            $monthly = $price && $price['currency'] === $currency ? static::monthly($price, (int) ($item->getAttribute('quantity') ?? 1)) : null;

            if ($monthly === null) {
                $summary['uncounted']++;

                continue;
            }

            $total += $monthly;
        }

        $summary['mrr'] = (int) round($total);

        return $summary;
    }
}

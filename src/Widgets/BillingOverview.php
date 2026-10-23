<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Widgets;

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use AmjadIqbal\FilamentBillingConsole\Support\Mrr;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * MRR, active and trialing subscriptions, and 30-day churn, from the Cashier tables plus Stripe prices (cached).
 */
class BillingOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return BillingConsole::can('overview.view');
    }

    protected function getStats(): array
    {
        $t = fn (string $key, array $replace = []): string => __("filament-billing-console::billing-console.overview.{$key}", $replace);
        $s = Mrr::summary();

        $mrrDescription = match (true) {
            $s['error'] !== null => $s['error'],
            $s['uncounted'] > 0 => trans_choice('filament-billing-console::billing-console.overview.uncounted', $s['uncounted'], ['count' => $s['uncounted']]),
            default => $t('mrr_help'),
        };

        return [
            Stat::make($t('mrr', ['currency' => strtoupper($s['currency'])]), $s['mrr'] === null ? '—' : BillingConsole::money($s['mrr'], $s['currency']))
                ->description($mrrDescription)
                ->color($s['error'] !== null ? 'danger' : 'gray'),
            Stat::make($t('active'), number_format($s['active']))
                ->description($s['past_due'] > 0 ? $t('past_due', ['count' => $s['past_due']]) : $t('active_help'))
                ->color($s['past_due'] > 0 ? 'danger' : 'gray'),
            Stat::make($t('trialing'), number_format($s['trialing']))
                ->description($t('trialing_help'))
                ->color('gray'),
            Stat::make($t('churn'), $s['churn_rate'] === null ? '—' : $s['churn_rate'].'%')
                ->description($t('churn_help', ['count' => $s['churned']]))
                ->color('gray'),
        ];
    }
}

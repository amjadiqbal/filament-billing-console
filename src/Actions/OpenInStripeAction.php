<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Actions;

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class OpenInStripeAction
{
    /**
     * A link to the record in the Stripe dashboard. $path receives the record and returns e.g. "customers/cus_123".
     */
    public static function make(Closure $path, string $name = 'openInStripe'): Action
    {
        return Action::make($name)
            ->label(__('filament-billing-console::billing-console.actions.open_in_stripe'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->url(function (mixed $record) use ($path): ?string {
                $resolved = $path($record);

                return filled($resolved) ? BillingConsole::dashboardUrl($resolved) : null;
            }, shouldOpenInNewTab: true)
            ->visible(fn (mixed $record): bool => filled($path($record)));
    }
}

<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource\Pages;

use AmjadIqbal\FilamentBillingConsole\Actions\OpenInStripeAction;
use AmjadIqbal\FilamentBillingConsole\Actions\SyncFromStripeAction;
use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource;
use AmjadIqbal\FilamentBillingConsole\Widgets\CustomerInvoicesTable;
use AmjadIqbal\FilamentBillingConsole\Widgets\CustomerPaymentsTable;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SyncFromStripeAction::make('customers'),
            OpenInStripeAction::make(fn (Model $record): ?string => $record->getAttribute('stripe_id') ? 'customers/'.$record->getAttribute('stripe_id') : null),
        ];
    }

    protected function getFooterWidgets(): array
    {
        $plugin = BillingConsole::plugin();

        return array_values(array_filter([
            $plugin->hasInvoices() && BillingConsole::can('invoices.viewAny') ? CustomerInvoicesTable::class : null,
            $plugin->hasPayments() && BillingConsole::can('payments.viewAny') ? CustomerPaymentsTable::class : null,
        ]));
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}

<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Resources\SubscriptionResource\Pages;

use AmjadIqbal\FilamentBillingConsole\Actions\OpenInStripeAction;
use AmjadIqbal\FilamentBillingConsole\Actions\SyncFromStripeAction;
use AmjadIqbal\FilamentBillingConsole\Resources\SubscriptionResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SyncFromStripeAction::make('subscriptions'),
            OpenInStripeAction::make(fn (Model $record): string => 'subscriptions/'.$record->getAttribute('stripe_id')),
        ];
    }
}

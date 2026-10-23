<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Resources\SubscriptionResource\Pages;

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use AmjadIqbal\FilamentBillingConsole\Resources\SubscriptionResource;
use AmjadIqbal\FilamentBillingConsole\Widgets\BillingOverview;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderWidgets(): array
    {
        return BillingConsole::can('overview.view') ? [BillingOverview::class] : [];
    }
}

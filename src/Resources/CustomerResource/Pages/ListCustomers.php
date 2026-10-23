<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource\Pages;

use AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
}

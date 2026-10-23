<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Widgets;

use AmjadIqbal\FilamentBillingConsole\Support\ApiTables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;

class CustomerPaymentsTable extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return ApiTables::payments($table, (string) $this->record?->getAttribute('stripe_id'))
            ->heading(__('filament-billing-console::billing-console.payments.title'));
    }
}

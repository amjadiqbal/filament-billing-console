<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource;

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use AmjadIqbal\FilamentBillingConsole\Resources\SubscriptionResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament-billing-console::billing-console.subscriptions.plural_model_label');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return BillingConsole::can('subscriptions.viewAny');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return SubscriptionResource::table($table)
            ->recordActions([
                Action::make('view')
                    ->label(__('filament-billing-console::billing-console.actions.view'))
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (Model $record): string => SubscriptionResource::getUrl('view', ['record' => $record]))
                    ->visible(fn (Model $record): bool => BillingConsole::plugin()->hasSubscriptions() && BillingConsole::can('subscriptions.view', $record)),
            ])
            ->filters([])
            ->headerActions([]);
    }
}

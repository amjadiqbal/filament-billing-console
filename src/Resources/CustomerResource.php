<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Resources;

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use AmjadIqbal\FilamentBillingConsole\Resources\Concerns\AuthorizesThroughConsole;
use AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource\Pages\ListCustomers;
use AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource\Pages\ViewCustomer;
use AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource\SubscriptionsRelationManager;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CustomerResource extends Resource
{
    use AuthorizesThroughConsole;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $slug = 'billing/customers';

    protected static bool $isScopedToTenant = false;

    protected static ?int $navigationSort = 1;

    protected static function consoleSubject(): string
    {
        return 'customers';
    }

    public static function getModel(): string
    {
        return BillingConsole::customerModel();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('stripe_id');
    }

    public static function getModelLabel(): string
    {
        return __('filament-billing-console::billing-console.customers.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-billing-console::billing-console.customers.plural_model_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return BillingConsole::plugin()->getNavigationGroup() ?? __('filament-billing-console::billing-console.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        $base = BillingConsole::plugin()->getNavigationSort();

        return $base === null ? static::$navigationSort : $base + (int) static::$navigationSort;
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record) {
            return static::getModelLabel();
        }

        $plugin = BillingConsole::plugin();

        return (string) ($record->getAttribute($plugin->getCustomerNameAttribute())
            ?: ($plugin->getCustomerEmailAttribute() ? $record->getAttribute($plugin->getCustomerEmailAttribute()) : null)
            ?: $record->getAttribute('stripe_id'));
    }

    public static function infolist(Schema $schema): Schema
    {
        $t = fn (string $key): string => __("filament-billing-console::billing-console.{$key}");
        $plugin = BillingConsole::plugin();

        return $schema->components([
            Section::make($t('customers.details'))
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema(array_values(array_filter([
                    TextEntry::make($plugin->getCustomerNameAttribute())->label($t('fields.name'))->placeholder('—'),
                    $plugin->getCustomerEmailAttribute()
                        ? TextEntry::make($plugin->getCustomerEmailAttribute())->label($t('fields.email'))->placeholder('—')->copyable()
                        : null,
                    TextEntry::make('stripe_id')->label($t('fields.stripe_id'))->copyable()->fontFamily('mono'),
                    TextEntry::make('pm_last_four')
                        ->label($t('customers.payment_method'))
                        ->formatStateUsing(fn (?string $state, Model $record): string => trim(ucfirst((string) $record->getAttribute('pm_type')).' •••• '.$state))
                        ->placeholder($t('customers.no_payment_method')),
                    TextEntry::make('trial_ends_at')->label($t('customers.generic_trial_ends'))->dateTime()->placeholder('—'),
                    TextEntry::make('created_at')->label($t('fields.created'))->dateTime()->placeholder('—'),
                ]))),
        ]);
    }

    public static function table(Table $table): Table
    {
        $t = fn (string $key): string => __("filament-billing-console::billing-console.{$key}");
        $plugin = BillingConsole::plugin();
        $email = $plugin->getCustomerEmailAttribute();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
                'subscriptions as active_subscriptions_count' => fn (Builder $q): Builder => $q->whereIn('stripe_status', ['active', 'trialing', 'past_due']),
            ]))
            ->columns([
                TextColumn::make($plugin->getCustomerNameAttribute())
                    ->label($t('fields.name'))
                    ->description($email ? fn (Model $record): ?string => $record->getAttribute($email) : null)
                    ->searchable(array_values(array_filter([$plugin->getCustomerNameAttribute(), $email])))
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('stripe_id')
                    ->label($t('fields.stripe_id'))
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->toggleable(),
                TextColumn::make('pm_last_four')
                    ->label($t('customers.payment_method'))
                    ->formatStateUsing(fn (?string $state, Model $record): string => trim(ucfirst((string) $record->getAttribute('pm_type')).' •••• '.$state))
                    ->placeholder('—'),
                TextColumn::make('active_subscriptions_count')
                    ->label($t('customers.active_subscriptions'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label($t('fields.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('subscribed')
                    ->label($t('customers.filter_subscribed'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('subscriptions', fn (Builder $q): Builder => $q->whereIn('stripe_status', ['active', 'trialing', 'past_due'])),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('subscriptions', fn (Builder $q): Builder => $q->whereIn('stripe_status', ['active', 'trialing', 'past_due'])),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            SubscriptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}

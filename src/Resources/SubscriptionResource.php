<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Resources;

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use AmjadIqbal\FilamentBillingConsole\Resources\Concerns\AuthorizesThroughConsole;
use AmjadIqbal\FilamentBillingConsole\Resources\SubscriptionResource\Pages\ListSubscriptions;
use AmjadIqbal\FilamentBillingConsole\Resources\SubscriptionResource\Pages\ViewSubscription;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Subscription;
use UnitEnum;

class SubscriptionResource extends Resource
{
    use AuthorizesThroughConsole;

    public const STATUSES = ['active', 'trialing', 'past_due', 'unpaid', 'incomplete', 'incomplete_expired', 'paused', 'canceled'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?string $slug = 'billing/subscriptions';

    protected static bool $isScopedToTenant = false;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'stripe_id';

    protected static function consoleSubject(): string
    {
        return 'subscriptions';
    }

    public static function getModel(): string
    {
        return BillingConsole::subscriptionModel();
    }

    public static function getModelLabel(): string
    {
        return __('filament-billing-console::billing-console.subscriptions.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-billing-console::billing-console.subscriptions.plural_model_label');
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

    /**
     * Past-due and unpaid subscriptions, shown next to the navigation item in gray.
     */
    public static function getNavigationBadge(): ?string
    {
        if (! BillingConsole::can('subscriptions.viewAny')) {
            return null;
        }

        $count = static::getModel()::query()->whereIn('stripe_status', ['past_due', 'unpaid'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'gray';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('filament-billing-console::billing-console.subscriptions.badge_tooltip');
    }

    public static function customerLabel(?Model $customer): ?string
    {
        if (! $customer) {
            return null;
        }

        return CustomerResource::getRecordTitle($customer);
    }

    public static function customerUrl(?Model $customer): ?string
    {
        if (! $customer || ! BillingConsole::plugin()->hasCustomers() || ! BillingConsole::can('customers.view', $customer)) {
            return null;
        }

        return CustomerResource::getUrl('view', ['record' => $customer]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $t = fn (string $key): string => __("filament-billing-console::billing-console.{$key}");

        return $schema->components([
            Section::make($t('subscriptions.details'))
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('owner')
                        ->label($t('fields.customer'))
                        ->state(fn (Subscription $record): ?string => static::customerLabel($record->owner))
                        ->url(fn (Subscription $record): ?string => static::customerUrl($record->owner))
                        ->placeholder('—'),
                    TextEntry::make('type')->label($t('subscriptions.type')),
                    TextEntry::make('stripe_status')
                        ->label($t('fields.status'))
                        ->formatStateUsing(fn (?string $state): string => BillingConsole::statusLabel($state))
                        ->color(fn (?string $state): string => BillingConsole::statusColor($state))
                        ->weight('medium'),
                    TextEntry::make('stripe_id')->label($t('fields.stripe_id'))->copyable()->fontFamily('mono'),
                    TextEntry::make('trial_ends_at')->label($t('subscriptions.trial_ends'))->dateTime()->placeholder('—'),
                    TextEntry::make('ends_at')
                        ->label($t('subscriptions.ends'))
                        ->dateTime()
                        ->placeholder('—')
                        ->helperText(fn (Subscription $record): ?string => $record->onGracePeriod() ? $t('subscriptions.grace_period') : null),
                    TextEntry::make('created_at')->label($t('fields.created'))->dateTime(),
                ]),
            Section::make($t('subscriptions.items'))
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema([
                            TextEntry::make('stripe_price')->label($t('subscriptions.price'))->fontFamily('mono')->copyable(),
                            TextEntry::make('stripe_product')->label($t('subscriptions.product'))->fontFamily('mono')->copyable(),
                            TextEntry::make('quantity')->label($t('subscriptions.quantity'))->placeholder('—'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $t = fn (string $key): string => __("filament-billing-console::billing-console.{$key}");
        $inCustomer = $table->getLivewire() instanceof RelationManager;
        $email = BillingConsole::plugin()->getCustomerEmailAttribute();
        $name = BillingConsole::plugin()->getCustomerNameAttribute();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['owner', 'items']))
            ->columns([
                TextColumn::make('owner_label')
                    ->label($t('fields.customer'))
                    ->state(fn (Subscription $record): ?string => static::customerLabel($record->owner))
                    ->url(fn (Subscription $record): ?string => static::customerUrl($record->owner))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('owner', function (Builder $q) use ($search, $email, $name): void {
                        $q->where(function (Builder $q) use ($search, $email, $name): void {
                            $q->where($name, 'like', "%{$search}%")->orWhere('stripe_id', 'like', "%{$search}%");

                            if ($email) {
                                $q->orWhere($email, 'like', "%{$search}%");
                            }
                        });
                    }))
                    ->placeholder('—')
                    ->weight('medium')
                    ->hidden($inCustomer),
                TextColumn::make('type')
                    ->label($t('subscriptions.type'))
                    ->searchable(),
                TextColumn::make('stripe_status')
                    ->label($t('fields.status'))
                    ->formatStateUsing(fn (?string $state): string => BillingConsole::statusLabel($state))
                    ->color(fn (?string $state): string => BillingConsole::statusColor($state))
                    ->weight('medium')
                    ->sortable(),
                TextColumn::make('stripe_price')
                    ->label($t('subscriptions.price'))
                    ->state(fn (Subscription $record): ?string => $record->stripe_price
                        ?? ($record->items->count() > 1 ? trans_choice('filament-billing-console::billing-console.subscriptions.prices_count', $record->items->count(), ['count' => $record->items->count()]) : null))
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('quantity')
                    ->label($t('subscriptions.quantity'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('trial_ends_at')
                    ->label($t('subscriptions.trial_ends'))
                    ->date()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('ends_at')
                    ->label($t('subscriptions.ends'))
                    ->date()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('stripe_id')
                    ->label($t('fields.stripe_id'))
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label($t('fields.created'))
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('stripe_status')
                    ->label($t('fields.status'))
                    ->multiple()
                    ->options(collect(static::STATUSES)->mapWithKeys(fn (string $s): array => [$s => BillingConsole::statusLabel($s)])->all()),
                TernaryFilter::make('ending')
                    ->label($t('subscriptions.filter_ending'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('ends_at')->where('ends_at', '>', now()),
                        false: fn (Builder $query): Builder => $query->where(fn (Builder $q): Builder => $q->whereNull('ends_at')->orWhere('ends_at', '<=', now())),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'view' => ViewSubscription::route('/{record}'),
        ];
    }
}

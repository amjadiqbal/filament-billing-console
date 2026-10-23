<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Pages;

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use AmjadIqbal\FilamentBillingConsole\Support\ApiTables;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The latest payments from the Stripe API (read-only, cached).
 */
class Payments extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $slug = 'billing/payments';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('filament-billing-console::billing-console.payments.title');
    }

    public function getTitle(): string
    {
        return __('filament-billing-console::billing-console.payments.title');
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

    public static function canAccess(): bool
    {
        return BillingConsole::plugin()->hasPayments() && BillingConsole::can('payments.viewAny');
    }

    public function table(Table $table): Table
    {
        return ApiTables::payments($table);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make(__('filament-billing-console::billing-console.payments.intro', ['count' => BillingConsole::plugin()->getMaxListRecords()]))->color('gray'),
            EmbeddedTable::make(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Support;

use AmjadIqbal\FilamentBillingConsole\Actions\OpenInStripeAction;
use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Tables over the Stripe API (invoices and payments). The latest records are loaded once (cached), then searched,
 * sorted and paginated in memory.
 */
class ApiTables
{
    public static function invoices(Table $table, ?string $customer = null): Table
    {
        $t = fn (string $key): string => __("filament-billing-console::billing-console.{$key}");
        $statuses = ['draft', 'open', 'paid', 'uncollectible', 'void'];

        return static::base($table)
            ->records(function (?string $search, ?array $filters, int|string $page, int|string $recordsPerPage, ?string $sortColumn, ?string $sortDirection) use ($customer): LengthAwarePaginator {
                $status = $filters['status']['value'] ?? null;
                $rows = StripeData::invoices($customer, filled($status) ? (string) $status : null);

                return static::paginate(static::withLocalCustomers($rows), $search, ['number', 'customer_email', 'customer_name', 'id'], $page, $recordsPerPage, $sortColumn, $sortDirection);
            })
            ->columns([
                TextColumn::make('number')
                    ->label($t('invoices.number'))
                    ->placeholder($t('invoices.draft_number'))
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('customer_email')
                    ->label($t('fields.customer'))
                    ->description(fn (array $record): ?string => $record['customer_name'] ?? null)
                    ->url(fn (array $record): ?string => static::customerUrl($record))
                    ->placeholder('—')
                    ->hidden($customer !== null),
                TextColumn::make('status')
                    ->label($t('fields.status'))
                    ->formatStateUsing(fn (?string $state): string => BillingConsole::statusLabel($state))
                    ->color(fn (?string $state): string => BillingConsole::statusColor($state))
                    ->weight('medium'),
                TextColumn::make('total')
                    ->label($t('invoices.total'))
                    ->formatStateUsing(fn (mixed $state, array $record): string => BillingConsole::money($state === null ? null : (int) $state, $record['currency']))
                    ->sortable(),
                TextColumn::make('amount_due')
                    ->label($t('invoices.amount_due'))
                    ->formatStateUsing(fn (mixed $state, array $record): string => BillingConsole::money($state === null ? null : (int) $state, $record['currency']))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created')
                    ->label($t('fields.created'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label($t('invoices.due'))
                    ->date()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label($t('fields.status'))
                    ->options(collect($statuses)->mapWithKeys(fn (string $s): array => [$s => BillingConsole::statusLabel($s)])->all()),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label($t('invoices.pdf'))
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->color('gray')
                    ->url(fn (array $record): ?string => $record['invoice_pdf'] ?? null, shouldOpenInNewTab: true)
                    ->visible(fn (array $record): bool => filled($record['invoice_pdf'] ?? null)),
                OpenInStripeAction::make(fn (array $record): string => 'invoices/'.$record['id']),
            ])
            ->defaultSort('created', 'desc');
    }

    public static function payments(Table $table, ?string $customer = null): Table
    {
        $t = fn (string $key): string => __("filament-billing-console::billing-console.{$key}");

        return static::base($table)
            ->records(function (?string $search, int|string $page, int|string $recordsPerPage, ?string $sortColumn, ?string $sortDirection, ?array $filters) use ($customer): LengthAwarePaginator {
                $rows = StripeData::payments($customer);
                $status = $filters['status']['value'] ?? null;

                if (filled($status)) {
                    $rows = array_values(array_filter($rows, fn (array $row): bool => $row['status'] === $status));
                }

                return static::paginate(static::withLocalCustomers($rows), $search, ['id', 'email', 'description', 'payment_intent'], $page, $recordsPerPage, $sortColumn, $sortDirection);
            })
            ->columns([
                TextColumn::make('amount')
                    ->label($t('payments.amount'))
                    ->formatStateUsing(fn (mixed $state, array $record): string => BillingConsole::money((int) $state, $record['currency']))
                    ->weight('medium')
                    ->sortable(),
                TextColumn::make('status')
                    ->label($t('fields.status'))
                    ->formatStateUsing(fn (?string $state): string => BillingConsole::statusLabel($state))
                    ->color(fn (?string $state): string => BillingConsole::statusColor($state))
                    ->description(fn (array $record): ?string => $record['failure_message'] ?? null)
                    ->weight('medium'),
                TextColumn::make('email')
                    ->label($t('fields.customer'))
                    ->url(fn (array $record): ?string => static::customerUrl($record))
                    ->placeholder('—')
                    ->searchable()
                    ->hidden($customer !== null),
                TextColumn::make('method')
                    ->label($t('payments.method'))
                    ->placeholder('—'),
                TextColumn::make('description')
                    ->label($t('payments.description'))
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('created')
                    ->label($t('fields.created'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label($t('fields.status'))
                    ->options(collect(['succeeded', 'pending', 'failed', 'refunded', 'partially_refunded'])
                        ->mapWithKeys(fn (string $s): array => [$s => BillingConsole::statusLabel($s)])->all()),
            ])
            ->recordActions([
                Action::make('receipt')
                    ->label($t('payments.receipt'))
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->color('gray')
                    ->url(fn (array $record): ?string => $record['receipt_url'] ?? null, shouldOpenInNewTab: true)
                    ->visible(fn (array $record): bool => filled($record['receipt_url'] ?? null)),
                OpenInStripeAction::make(fn (array $record): string => 'payments/'.($record['payment_intent'] ?? $record['id'])),
            ])
            ->defaultSort('created', 'desc');
    }

    protected static function base(Table $table): Table
    {
        return $table
            ->searchable()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon(Heroicon::OutlinedBanknotes)
            ->emptyStateHeading(fn (): string => StripeGateway::lastError() ? __('filament-billing-console::billing-console.errors.heading') : __('filament-billing-console::billing-console.empty.heading'))
            ->emptyStateDescription(fn (): ?string => StripeGateway::lastError());
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $searchIn
     */
    public static function paginate(array $rows, ?string $search, array $searchIn, int|string $page, int|string $perPage, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator
    {
        $collection = Collection::make($rows);

        if (filled($search)) {
            $needle = mb_strtolower(trim($search));
            $collection = $collection->filter(function (array $row) use ($needle, $searchIn): bool {
                foreach ($searchIn as $field) {
                    if (str_contains(mb_strtolower((string) ($row[$field] ?? '')), $needle)) {
                        return true;
                    }
                }

                return false;
            });
        }

        if ($sortColumn) {
            $collection = $collection->sortBy(fn (array $row): mixed => $row[$sortColumn] ?? null, SORT_REGULAR, $sortDirection === 'desc');
        }

        $perPage = $perPage === 'all' ? max(1, $collection->count()) : max(1, (int) $perPage);
        $page = max(1, (int) $page);

        return new LengthAwarePaginator(
            $collection->values()->forPage($page, $perPage)->keyBy('id')->all(),
            $collection->count(),
            $perPage,
            $page,
        );
    }

    /**
     * Adds the local customer's key to each row whose Stripe customer is in the Cashier tables.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected static function withLocalCustomers(array $rows): array
    {
        $ids = array_values(array_unique(array_filter(array_column($rows, 'customer'))));

        if ($ids === [] || ! BillingConsole::plugin()->hasCustomers()) {
            return $rows;
        }

        $model = BillingConsole::customerModel();
        $keys = $model::query()->whereIn('stripe_id', $ids)->pluck((new $model)->getKeyName(), 'stripe_id');

        return array_map(fn (array $row): array => [...$row, 'local_customer' => $keys[$row['customer'] ?? ''] ?? null], $rows);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected static function customerUrl(array $record): ?string
    {
        if (blank($record['local_customer'] ?? null) || ! BillingConsole::can('customers.view')) {
            return null;
        }

        return CustomerResource::getUrl('view', ['record' => $record['local_customer']]);
    }
}

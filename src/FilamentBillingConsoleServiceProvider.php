<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole;

use AmjadIqbal\FilamentBillingConsole\Widgets\BillingOverview;
use AmjadIqbal\FilamentBillingConsole\Widgets\CustomerInvoicesTable;
use AmjadIqbal\FilamentBillingConsole\Widgets\CustomerPaymentsTable;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentBillingConsoleServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-billing-console';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        // Registered by name, so the widgets' Livewire updates resolve wherever they are placed, including a custom
        // dashboard that adds them by class.
        Livewire::component('amjadiqbal.filament-billing-console.billing-overview', BillingOverview::class);
        Livewire::component('amjadiqbal.filament-billing-console.customer-invoices-table', CustomerInvoicesTable::class);
        Livewire::component('amjadiqbal.filament-billing-console.customer-payments-table', CustomerPaymentsTable::class);
    }
}

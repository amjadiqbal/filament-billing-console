<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole;

use AmjadIqbal\FilamentBillingConsole\Pages\Invoices;
use AmjadIqbal\FilamentBillingConsole\Pages\Payments;
use AmjadIqbal\FilamentBillingConsole\Resources\CustomerResource;
use AmjadIqbal\FilamentBillingConsole\Resources\SubscriptionResource;
use AmjadIqbal\FilamentBillingConsole\Widgets\BillingOverview;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Throwable;

class BillingConsolePlugin implements Plugin
{
    public const ID = 'billing-console';

    protected bool $hasCustomers = true;

    protected bool $hasSubscriptions = true;

    protected bool $hasInvoices = true;

    protected bool $hasPayments = true;

    protected bool $hasDashboardWidget = true;

    protected ?string $navigationGroup = null;

    protected ?int $navigationSort = null;

    protected ?Closure $authorizeUsing = null;

    protected int $cacheSeconds = 300;

    protected int $maxListRecords = 100;

    protected ?string $currency = null;

    protected int $syncsPerMinute = 10;

    protected string $customerNameAttribute = 'name';

    protected ?string $customerEmailAttribute = 'email';

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * The plugin registered on the current panel, or a default instance outside a panel.
     */
    public static function get(): static
    {
        try {
            $panel = Filament::getCurrentOrDefaultPanel();

            if ($panel?->hasPlugin(static::ID)) {
                /** @var static */
                return $panel->getPlugin(static::ID);
            }
        } catch (Throwable) {
            // No panels registered.
        }

        return app(static::class);
    }

    public function getId(): string
    {
        return static::ID;
    }

    public function register(Panel $panel): void
    {
        $panel->resources(array_values(array_filter([
            $this->hasCustomers ? CustomerResource::class : null,
            $this->hasSubscriptions ? SubscriptionResource::class : null,
        ])));

        $panel->pages(array_values(array_filter([
            $this->hasInvoices ? Invoices::class : null,
            $this->hasPayments ? Payments::class : null,
        ])));

        if ($this->hasDashboardWidget) {
            $panel->widgets([BillingOverview::class]);
        }
    }

    public function boot(Panel $panel): void {}

    public function customers(bool $condition = true): static
    {
        $this->hasCustomers = $condition;

        return $this;
    }

    public function hasCustomers(): bool
    {
        return $this->hasCustomers;
    }

    public function subscriptions(bool $condition = true): static
    {
        $this->hasSubscriptions = $condition;

        return $this;
    }

    public function hasSubscriptions(): bool
    {
        return $this->hasSubscriptions;
    }

    public function invoices(bool $condition = true): static
    {
        $this->hasInvoices = $condition;

        return $this;
    }

    public function hasInvoices(): bool
    {
        return $this->hasInvoices;
    }

    public function payments(bool $condition = true): static
    {
        $this->hasPayments = $condition;

        return $this;
    }

    public function hasPayments(): bool
    {
        return $this->hasPayments;
    }

    /**
     * Show the MRR and churn summary on the panel dashboard. It is always shown above the subscriptions table.
     */
    public function dashboardWidget(bool $condition = true): static
    {
        $this->hasDashboardWidget = $condition;

        return $this;
    }

    public function hasDashboardWidget(): bool
    {
        return $this->hasDashboardWidget;
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    /**
     * Decide who may see what. The closure receives `$ability` (see BillingConsole::ABILITIES), `$record` (a customer
     * or subscription model, or null) and `$user`, and returns a bool. It replaces the gate and policy checks.
     */
    public function authorizeUsing(?Closure $callback): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    public function getAuthorizeUsing(): ?Closure
    {
        return $this->authorizeUsing;
    }

    /**
     * How long Stripe API reads are cached, in seconds. 0 turns the cache off.
     */
    public function cacheFor(int $seconds): static
    {
        $this->cacheSeconds = max(0, $seconds);

        return $this;
    }

    public function getCacheSeconds(): int
    {
        return $this->cacheSeconds;
    }

    /**
     * How many of the latest invoices or payments one API table loads (Stripe's maximum page is 100).
     */
    public function maxListRecords(int $max): static
    {
        $this->maxListRecords = min(100, max(1, $max));

        return $this;
    }

    public function getMaxListRecords(): int
    {
        return $this->maxListRecords;
    }

    /**
     * The currency MRR is reported in. Defaults to config('cashier.currency').
     */
    public function currency(?string $currency): static
    {
        $this->currency = $currency !== null ? strtolower($currency) : null;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency ?? strtolower((string) config('cashier.currency', 'usd'));
    }

    /**
     * How many "Sync from Stripe" actions one user may run per minute.
     */
    public function syncsPerMinute(int $limit): static
    {
        $this->syncsPerMinute = max(1, $limit);

        return $this;
    }

    public function getSyncsPerMinute(): int
    {
        return $this->syncsPerMinute;
    }

    /**
     * The customer model's columns shown and searched as name and email. Pass null for the email if there is none.
     */
    public function customerAttributes(string $name = 'name', ?string $email = 'email'): static
    {
        $this->customerNameAttribute = $name;
        $this->customerEmailAttribute = $email;

        return $this;
    }

    public function getCustomerNameAttribute(): string
    {
        return $this->customerNameAttribute;
    }

    public function getCustomerEmailAttribute(): ?string
    {
        return $this->customerEmailAttribute;
    }
}

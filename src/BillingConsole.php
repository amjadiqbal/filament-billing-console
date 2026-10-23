<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;
use ReflectionMethod;
use Throwable;

class BillingConsole
{
    /**
     * Abilities the console checks. `*.sync` only writes Stripe's own data into your Cashier tables.
     */
    public const ABILITIES = [
        'customers.viewAny', 'customers.view', 'customers.sync',
        'subscriptions.viewAny', 'subscriptions.view', 'subscriptions.sync',
        'invoices.viewAny', 'payments.viewAny', 'overview.view',
    ];

    /**
     * An app-wide gate that, when defined, every ability must pass first.
     */
    public const GATE = 'viewBillingConsole';

    public static function plugin(): BillingConsolePlugin
    {
        return BillingConsolePlugin::get();
    }

    /**
     * @return class-string<Model>
     */
    public static function customerModel(): string
    {
        /** @var class-string<Model> */
        return Cashier::$customerModel;
    }

    /**
     * @return class-string<Subscription>
     */
    public static function subscriptionModel(): string
    {
        /** @var class-string<Subscription> */
        return Cashier::$subscriptionModel;
    }

    public static function user(): ?Authenticatable
    {
        try {
            return Filament::auth()->user();
        } catch (Throwable) {
            return auth()->user();
        }
    }

    /**
     * Whether the current user may do `$ability`: the plugin's authorizeUsing() closure if set; otherwise the
     * viewBillingConsole gate if defined, then the model's policy (viewAny / view; sync falls back to update, then
     * view); otherwise allowed, because panel access is already gated by canAccessPanel().
     */
    public static function can(string $ability, ?Model $record = null): bool
    {
        $user = static::user();

        if ($callback = static::plugin()->getAuthorizeUsing()) {
            return (bool) app()->call($callback, ['ability' => $ability, 'record' => $record, 'user' => $user]);
        }

        $gate = $user ? Gate::forUser($user) : Gate::getFacadeRoot();

        if (Gate::has(static::GATE) && $gate->denies(static::GATE)) {
            return false;
        }

        [$subject, $action] = array_pad(explode('.', $ability, 2), 2, 'viewAny');

        $model = match ($subject) {
            'customers' => static::customerModel(),
            'subscriptions' => static::subscriptionModel(),
            default => null,
        };

        if (! $model || ! ($policy = Gate::getPolicyFor($model))) {
            return true;
        }

        if ($action === 'sync') {
            $action = method_exists($policy, 'sync') ? 'sync' : (method_exists($policy, 'update') ? 'update' : 'view');
        }

        if (! method_exists($policy, $action)) {
            return true;
        }

        if (! $record && (new ReflectionMethod($policy, $action))->getNumberOfRequiredParameters() > 1) {
            return true;
        }

        return $gate->allows($action, $record ?? $model);
    }

    /**
     * Whether the configured key is a test-mode key. Only the prefix is read; the key itself is never shown.
     */
    public static function isTestMode(): bool
    {
        $key = (string) config('cashier.secret');

        return str_starts_with($key, 'sk_test_') || str_starts_with($key, 'rk_test_');
    }

    public static function hasSecret(): bool
    {
        return filled(config('cashier.secret'));
    }

    /**
     * A link to the object in the Stripe dashboard, in test or live mode to match the key.
     */
    public static function dashboardUrl(string $path): string
    {
        return 'https://dashboard.stripe.com/'.(static::isTestMode() ? 'test/' : '').ltrim($path, '/');
    }

    /**
     * Amount in the smallest currency unit, formatted the way Cashier formats it.
     */
    public static function money(?int $amount, ?string $currency): string
    {
        if ($amount === null) {
            return '—';
        }

        try {
            return Cashier::formatAmount($amount, $currency ?: static::plugin()->getCurrency());
        } catch (Throwable) {
            return number_format($amount / 100, 2).' '.strtoupper((string) $currency);
        }
    }

    /**
     * Text colour for a Stripe status (subscription, invoice or payment). Colours are applied as text only.
     */
    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'active', 'paid', 'succeeded' => 'success',
            'trialing', 'open', 'pending', 'processing' => 'info',
            'past_due', 'unpaid', 'incomplete', 'uncollectible', 'failed', 'requires_payment_method' => 'danger',
            default => 'gray',
        };
    }

    public static function statusLabel(?string $status): string
    {
        $key = 'filament-billing-console::billing-console.status.'.$status;
        $label = __($key);

        return $label === $key ? str((string) $status)->replace('_', ' ')->ucfirst()->toString() : $label;
    }
}

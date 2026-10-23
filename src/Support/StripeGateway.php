<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Support;

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use Closure;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\PermissionException;
use Stripe\Exception\RateLimitException;
use Stripe\StripeClient;
use Throwable;

/**
 * Read-only access to the Stripe API: every call is cached, retried on network errors, and turned into a safe message
 * on failure. It never creates, updates or deletes anything in Stripe.
 */
class StripeGateway
{
    protected static ?string $lastError = null;

    public static function client(): StripeClient
    {
        return Cashier::stripe(['max_network_retries' => 2]);
    }

    /**
     * The error from the latest failed call in this request, safe to show (no key material), or null.
     */
    public static function lastError(): ?string
    {
        return static::$lastError;
    }

    public static function clearError(): void
    {
        static::$lastError = null;
    }

    /**
     * Run a read against Stripe, cached per key and arguments. Failures are not cached; they return $default and set
     * lastError().
     *
     * @template T
     *
     * @param  Closure(StripeClient): T  $read
     * @param  T  $default
     * @return T
     */
    public static function read(string $key, Closure $read, mixed $default = null): mixed
    {
        if (! BillingConsole::hasSecret()) {
            static::$lastError = __('filament-billing-console::billing-console.errors.no_secret');

            return $default;
        }

        $seconds = BillingConsole::plugin()->getCacheSeconds();
        $cacheKey = 'filament-billing-console:'.(BillingConsole::isTestMode() ? 'test' : 'live').':'.$key;

        try {
            if ($seconds === 0) {
                return $read(static::client());
            }

            return Cache::remember($cacheKey, $seconds, fn () => $read(static::client()));
        } catch (Throwable $e) {
            static::$lastError = static::describe($e);
            report($e);

            return $default;
        }
    }

    public static function forget(string $key): void
    {
        Cache::forget('filament-billing-console:'.(BillingConsole::isTestMode() ? 'test' : 'live').':'.$key);
    }

    /**
     * A message for the panel. Stripe's own messages can quote part of the key, so key-like strings are masked.
     */
    public static function describe(Throwable $e): string
    {
        $t = fn (string $key): string => __("filament-billing-console::billing-console.errors.{$key}");

        $message = match (true) {
            $e instanceof AuthenticationException => $t('authentication'),
            $e instanceof PermissionException => $t('permission'),
            $e instanceof RateLimitException => $t('rate_limit'),
            $e instanceof ApiConnectionException => $t('connection'),
            $e instanceof ApiErrorException => $t('api').' '.$e->getMessage(),
            default => $t('unexpected'),
        };

        return static::mask($message);
    }

    public static function mask(string $text): string
    {
        return (string) preg_replace('/\b(sk|rk|pk|whsec)_(test_|live_)?[A-Za-z0-9*]+/', '[key hidden]', $text);
    }
}

<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Actions;

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use AmjadIqbal\FilamentBillingConsole\Support\StripeGateway;
use AmjadIqbal\FilamentBillingConsole\Support\SubscriptionSync;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Cashier\Subscription;
use Throwable;

/**
 * Reads the record from Stripe and stores Stripe's values in the Cashier tables. Nothing is sent to Stripe.
 */
class SyncFromStripeAction
{
    public static function make(string $subject): Action
    {
        $t = fn (string $key, array $replace = []): string => __("filament-billing-console::billing-console.{$key}", $replace);

        return Action::make('syncFromStripe')
            ->label($t('actions.sync'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedArrowPath)
            ->modalIconColor('gray')
            ->modalHeading($t('actions.sync_heading'))
            ->modalDescription($t("actions.sync_description_{$subject}"))
            ->modalSubmitActionLabel($t('actions.sync'))
            ->visible(fn (?Model $record): bool => BillingConsole::can("{$subject}.sync", $record))
            ->action(function (Model $record) use ($subject, $t): void {
                abort_unless(BillingConsole::can("{$subject}.sync", $record), 403);

                $key = 'filament-billing-console:sync:'.(BillingConsole::user()?->getAuthIdentifier() ?? 'guest');

                if (RateLimiter::tooManyAttempts($key, BillingConsole::plugin()->getSyncsPerMinute())) {
                    Notification::make()
                        ->title($t('notifications.sync_throttled', ['seconds' => RateLimiter::availableIn($key)]))
                        ->danger()
                        ->send();

                    return;
                }

                RateLimiter::hit($key, 60);

                try {
                    if ($record instanceof Subscription) {
                        SubscriptionSync::subscription($record);
                        $body = null;
                    } else {
                        $count = SubscriptionSync::customer($record);
                        $body = trans_choice('filament-billing-console::billing-console.notifications.synced_subscriptions', $count, ['count' => $count]);
                    }
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title($t('notifications.sync_failed'))
                        ->body(StripeGateway::describe($e))
                        ->danger()
                        ->send();

                    return;
                }

                $record->refresh();

                Notification::make()
                    ->title($t('notifications.synced'))
                    ->body($body)
                    ->success()
                    ->send();
            });
    }
}

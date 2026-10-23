<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Tests;

use AmjadIqbal\FilamentBillingConsole\FilamentBillingConsoleServiceProvider;
use AmjadIqbal\FilamentBillingConsole\Support\StripeGateway;
use AmjadIqbal\FilamentBillingConsole\Tests\Fixtures\AdminPanelProvider;
use AmjadIqbal\FilamentBillingConsole\Tests\Fixtures\FakeStripe;
use AmjadIqbal\FilamentBillingConsole\Tests\Fixtures\User;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Kirschbaum\PowerJoins\PowerJoinsServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\CashierServiceProvider;
use Laravel\Cashier\Subscription;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Stripe\ApiRequestor;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeStripe::reset();
        ApiRequestor::setHttpClient(new FakeStripe);
        StripeGateway::clearError();
        Cache::flush();
        RateLimiter::clear('filament-billing-console:sync:1');
        Cashier::useCustomerModel(User::class);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            PowerJoinsServiceProvider::class,
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            'Filament\\QueryBuilder\\QueryBuilderServiceProvider',
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            CashierServiceProvider::class,
            FilamentBillingConsoleServiceProvider::class,
            AdminPanelProvider::class,
        ];

        return array_values(array_filter($providers, 'class_exists'));
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('cashier.secret', 'sk_test_fixture000000');
        $app['config']->set('cashier.currency', 'usd');
        $app['config']->set('cache.default', 'array');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::dropAllTables();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        foreach (glob(__DIR__.'/../vendor/laravel/cashier/database/migrations/*.php') ?: [] as $file) {
            (require $file)->up();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeUser(string $name = 'Admin', array $attributes = []): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'password' => 'secret-password',
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeSubscription(User $user, string $stripeId, string $status = 'active', string $price = 'price_pro_monthly', array $attributes = []): Subscription
    {
        /** @var Subscription $subscription */
        $subscription = $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => $stripeId,
            'stripe_status' => $status,
            'stripe_price' => $price,
            'quantity' => 1,
            ...$attributes,
        ]);

        $subscription->items()->create([
            'stripe_id' => 'si_'.substr($stripeId, 4),
            'stripe_product' => 'prod_pro',
            'stripe_price' => $price,
            'quantity' => $attributes['quantity'] ?? 1,
        ]);

        return $subscription;
    }
}

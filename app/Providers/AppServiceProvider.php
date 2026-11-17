<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Billing\CostCalculator;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\WebhookSignature;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PixelforgeClient::class, fn () => new PixelforgeClient(
            baseUrl: config('services.pixelforge.base_url'),
            apiKey: config('services.pixelforge.key'),
            timeout: config('services.pixelforge.timeout'),
        ));

        $this->app->singleton(WebhookSignature::class, fn () => new WebhookSignature(
            secret: (string) config('services.pixelforge.webhook_secret'),
            toleranceSeconds: config('services.pixelforge.webhook_tolerance'),
        ));

        $this->app->singleton(CostCalculator::class, fn () => new CostCalculator(
            markup: (string) config('billing.markup'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // `composer dev` also runs the scheduler, which polls for missed webhooks.
        if ($this->app->runningInConsole()) {
            DevCommands::artisan('schedule:work', 'scheduler');
        }

        Gate::define('view-admin', fn (User $user) => $user->is_admin
            || in_array(strtolower($user->email), (array) config('billing.admin_emails'), true));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

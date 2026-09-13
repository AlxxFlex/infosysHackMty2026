<?php

namespace App\Providers;

use App\Contracts\ExplanationProvider;
use App\Services\HttpExplanationProvider;
use App\Services\NoneExplanationProvider;
use App\Support\CourierConfigValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // API routes apply Laravel's built-in fixed-window throttle middleware.
        $this->app->bind(ExplanationProvider::class, function (): ExplanationProvider {
            return strtolower((string) config('courier.explanation.provider', 'none')) === 'none'
                ? new NoneExplanationProvider
                : new HttpExplanationProvider;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(CourierConfigValidator $validator): void
    {
        $errors = $validator->errors();
        if ($errors === []) {
            return;
        }

        // Local/test environments keep booting so the diagnostic command can
        // report every issue at once. Production fails closed with the same
        // actionable list rather than serving a partially configured app.
        if ($this->app->environment('production')) {
            $validator->assertValid();
        }

        Log::warning('courier.configuration_invalid', ['errors' => $errors]);
    }
}

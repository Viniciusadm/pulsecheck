<?php

namespace App\Providers;

use App\Models\InstanceSettings;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(InstanceSettings::CURRENT_BINDING, fn (): ?InstanceSettings => InstanceSettings::query()->first());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePasswordRules();
        $this->configurePasswordResetUrl();
        $this->configureRateLimiting();
        $this->configureRouteBindings();
        $this->configureGates();
    }

    private function configureRouteBindings(): void
    {
        Route::pattern('monitor', '[0-9]+');
        Route::pattern('notificationChannel', '[0-9]+');
        Route::pattern('user', '[0-9]+');
        Route::pattern('team', '[0-9]+');
    }

    private function configureGates(): void
    {
        Gate::define('manage-users', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-resources', fn (User $user): bool => $user->canManageResources());
    }

    private function configurePasswordRules(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(8)->letters()->numbers();

            return $this->app->isProduction()
                ? $rule->mixedCase()->uncompromised()
                : $rule;
        });
    }

    private function configurePasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return config('app.frontend_url').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('setup', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('forgot-password', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('reset-password', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('notification-test', fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->user()?->id ?: $request->ip()));

        RateLimiter::for('notification-channel-writes', fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->user()?->id ?: $request->ip()));

        RateLimiter::for('monitor-writes', fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->user()?->id ?: $request->ip()));
    }
}

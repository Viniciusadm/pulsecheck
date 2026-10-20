<?php

namespace App\Providers;

use App\Mail\InstanceMailConfigurator;
use App\Models\InstanceSettings;
use App\Models\MailSettings;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
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
        $this->app->scoped(MailSettings::CURRENT_BINDING, fn (): ?MailSettings => MailSettings::query()->oldest('id')->first());
        $this->app->singleton(InstanceMailConfigurator::class);
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
        $this->configureInstanceMailer();
    }

    private function configureInstanceMailer(): void
    {
        $this->app->afterResolving('mail.manager', function (MailFactory $manager): void {
            $this->app->make(InstanceMailConfigurator::class)->apply($manager);
        });

        Event::listen(JobProcessing::class, function (): void {
            if ($this->app->resolved('mail.manager')) {
                MailSettings::forgetCurrent();
                $this->app->make(InstanceMailConfigurator::class)->apply($this->app->make('mail.manager'));
            }
        });
    }

    private function configureRouteBindings(): void
    {
        Route::pattern('monitor', '[0-9]+');
        Route::pattern('notificationChannel', '[0-9]+');
        Route::pattern('user', '[0-9]+');
        Route::pattern('team', '[0-9]+');
        Route::pattern('token', '[A-Za-z0-9]{40}');
        Route::pattern('incident', '[0-9]+');
        Route::pattern('note', '[0-9]+');
        Route::pattern('maintenanceWindow', '[0-9]+');
        Route::pattern('statusPage', '[0-9]+');
        Route::pattern('badgeToken', '[A-Za-z0-9]{40}');
        Route::pattern('slug', '[a-z0-9-]{1,60}');
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

        RateLimiter::for('heartbeat', fn (Request $request): Limit => Limit::perMinute(30)->by($request->route('token').'|'.$request->ip()));

        RateLimiter::for('incident-writes', fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->user()?->id ?: $request->ip()));

        RateLimiter::for('maintenance-writes', fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->user()?->id ?: $request->ip()));

        RateLimiter::for('status-page-writes', fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->user()?->id ?: $request->ip()));

        RateLimiter::for('public-status', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('badges', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));

        RateLimiter::for('dashboard-writes', fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->user()?->id ?: $request->ip()));
    }
}

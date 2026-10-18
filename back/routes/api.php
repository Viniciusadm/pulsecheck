<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CurrentUserController;
use App\Http\Controllers\DashboardSummaryController;
use App\Http\Controllers\InstanceSettingsController;
use App\Http\Controllers\MonitorCheckResultController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\MonitorIncidentController;
use App\Http\Controllers\MonitorLatencyController;
use App\Http\Controllers\MonitorPauseController;
use App\Http\Controllers\MonitorResumeController;
use App\Http\Controllers\MonitorStatsController;
use App\Http\Controllers\MonitorUptimeHistoryController;
use App\Http\Controllers\NotificationChannelController;
use App\Http\Controllers\NotificationChannelTestController;
use App\Http\Controllers\PublicConfigController;
use App\Http\Controllers\RecentIncidentController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureSetupIsPending;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:sanctum')
        ->name('logout');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:forgot-password')
        ->name('forgot-password');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:reset-password')
        ->name('reset-password');
});

Route::get('config', PublicConfigController::class)->name('config');

Route::post('setup', SetupController::class)
    ->middleware(['throttle:setup', EnsureSetupIsPending::class])
    ->name('setup');

Route::get('user', CurrentUserController::class)
    ->middleware('auth:sanctum')
    ->name('user');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('dashboard/summary', DashboardSummaryController::class)->name('dashboard.summary');
    Route::get('incidents', RecentIncidentController::class)->name('incidents.recent');

    Route::get('teams', [TeamController::class, 'index'])->name('teams.index');

    Route::get('notification-channels', [NotificationChannelController::class, 'index'])->name('notification-channels.index');
    Route::post('notification-channels', [NotificationChannelController::class, 'store'])->middleware(['can:manage-resources', 'throttle:notification-channel-writes'])->name('notification-channels.store');

    Route::prefix('notification-channels/{notificationChannel}')->name('notification-channels.')->middleware('can:update,notificationChannel')->group(function () {
        Route::match(['put', 'patch'], '/', [NotificationChannelController::class, 'update'])->middleware('throttle:notification-channel-writes')->name('update');
        Route::delete('/', [NotificationChannelController::class, 'destroy'])->middleware('throttle:notification-channel-writes')->name('destroy');
        Route::post('test', NotificationChannelTestController::class)->middleware('throttle:notification-test')->name('test');
    });

    Route::middleware('can:manage-users')->group(function () {
        Route::put('instance', [InstanceSettingsController::class, 'update'])->name('instance.update');

        Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
        Route::match(['put', 'patch'], 'teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::get('monitors', [MonitorController::class, 'index'])->name('monitors.index');
    Route::post('monitors', [MonitorController::class, 'store'])->middleware(['can:manage-resources', 'throttle:monitor-writes'])->name('monitors.store');

    Route::prefix('monitors/{monitor}')->name('monitors.')->scopeBindings()->group(function () {
        Route::middleware('can:view,monitor')->group(function () {
            Route::get('/', [MonitorController::class, 'show'])->name('show');
            Route::get('checks', [MonitorCheckResultController::class, 'index'])->name('checks.index');
            Route::get('incidents', [MonitorIncidentController::class, 'index'])->name('incidents.index');
            Route::get('stats', MonitorStatsController::class)->name('stats');
            Route::get('latency', MonitorLatencyController::class)->name('latency');
            Route::get('uptime-history', MonitorUptimeHistoryController::class)->name('uptime-history');
        });

        Route::middleware(['can:update,monitor', 'throttle:monitor-writes'])->group(function () {
            Route::match(['put', 'patch'], '/', [MonitorController::class, 'update'])->name('update');
            Route::delete('/', [MonitorController::class, 'destroy'])->name('destroy');
            Route::post('pause', MonitorPauseController::class)->name('pause');
            Route::post('resume', MonitorResumeController::class)->name('resume');
        });
    });
});

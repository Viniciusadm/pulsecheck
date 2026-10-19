<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CurrentUserController;
use App\Http\Controllers\DashboardErrorBreakdownController;
use App\Http\Controllers\DashboardIncidentTimelineController;
use App\Http\Controllers\DashboardLatencyTrendController;
use App\Http\Controllers\DashboardLayoutController;
use App\Http\Controllers\DashboardSlowestMonitorsController;
use App\Http\Controllers\DashboardSummaryController;
use App\Http\Controllers\DashboardUptimeTrendController;
use App\Http\Controllers\HeartbeatController;
use App\Http\Controllers\IncidentAcknowledgementController;
use App\Http\Controllers\IncidentNoteController;
use App\Http\Controllers\InstanceSettingsController;
use App\Http\Controllers\MaintenanceWindowController;
use App\Http\Controllers\MonitorBadgeTokenController;
use App\Http\Controllers\MonitorCheckResultController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\MonitorHeartbeatTokenController;
use App\Http\Controllers\MonitorIncidentController;
use App\Http\Controllers\MonitorLatencyController;
use App\Http\Controllers\MonitorPauseController;
use App\Http\Controllers\MonitorResumeController;
use App\Http\Controllers\MonitorStatsController;
use App\Http\Controllers\MonitorUptimeHistoryController;
use App\Http\Controllers\NotificationChannelController;
use App\Http\Controllers\NotificationChannelPreviewController;
use App\Http\Controllers\NotificationChannelTestController;
use App\Http\Controllers\PublicBadgeController;
use App\Http\Controllers\PublicConfigController;
use App\Http\Controllers\PublicStatusPageController;
use App\Http\Controllers\RecentIncidentController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StatusPageController;
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

Route::prefix('public')->name('public.')->group(function () {
    Route::get('status-pages/{slug}', PublicStatusPageController::class)->middleware('throttle:public-status')->name('status-pages.show');
    Route::get('badges/{badgeToken}/status.svg', [PublicBadgeController::class, 'status'])->middleware('throttle:badges')->name('badges.status');
    Route::get('badges/{badgeToken}/uptime.svg', [PublicBadgeController::class, 'uptime'])->middleware('throttle:badges')->name('badges.uptime');
});

Route::match(['get', 'post', 'head'], 'heartbeat/{token}', HeartbeatController::class)
    ->middleware('throttle:heartbeat')
    ->name('heartbeat');

Route::post('setup', SetupController::class)
    ->middleware(['throttle:setup', EnsureSetupIsPending::class])
    ->name('setup');

Route::get('user', CurrentUserController::class)
    ->middleware('auth:sanctum')
    ->name('user');

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('summary', DashboardSummaryController::class)->name('summary');
        Route::get('uptime-trend', DashboardUptimeTrendController::class)->name('uptime-trend');
        Route::get('latency-trend', DashboardLatencyTrendController::class)->name('latency-trend');
        Route::get('slowest-monitors', DashboardSlowestMonitorsController::class)->name('slowest-monitors');
        Route::get('incident-timeline', DashboardIncidentTimelineController::class)->name('incident-timeline');
        Route::get('error-breakdown', DashboardErrorBreakdownController::class)->name('error-breakdown');
    });

    Route::prefix('me/dashboard')->name('me.dashboard.')->controller(DashboardLayoutController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::put('/', 'update')->middleware('throttle:dashboard-writes')->name('update');
        Route::delete('/', 'destroy')->middleware('throttle:dashboard-writes')->name('destroy');
    });

    Route::get('incidents', RecentIncidentController::class)->name('incidents.recent');

    Route::get('teams', [TeamController::class, 'index'])->name('teams.index');

    Route::get('notification-channels', [NotificationChannelController::class, 'index'])->name('notification-channels.index');
    Route::get('notification-channels/preview', NotificationChannelPreviewController::class)->middleware('can:manage-resources')->name('notification-channels.preview');
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

    Route::prefix('incidents/{incident}')->name('incidents.')->middleware('can:view,incident')->scopeBindings()->group(function () {
        Route::post('acknowledge', IncidentAcknowledgementController::class)->middleware('throttle:incident-writes')->name('acknowledge');
        Route::get('notes', [IncidentNoteController::class, 'index'])->name('notes.index');
        Route::post('notes', [IncidentNoteController::class, 'store'])->middleware('throttle:incident-writes')->name('notes.store');
        Route::delete('notes/{note}', [IncidentNoteController::class, 'destroy'])->middleware('throttle:incident-writes')->name('notes.destroy');
    });

    Route::get('status-pages', [StatusPageController::class, 'index'])->name('status-pages.index');
    Route::post('status-pages', [StatusPageController::class, 'store'])->middleware(['can:manage-resources', 'throttle:status-page-writes'])->name('status-pages.store');
    Route::get('status-pages/{statusPage}', [StatusPageController::class, 'show'])->middleware('can:view,statusPage')->name('status-pages.show');
    Route::match(['put', 'patch'], 'status-pages/{statusPage}', [StatusPageController::class, 'update'])->middleware(['can:update,statusPage', 'throttle:status-page-writes'])->name('status-pages.update');
    Route::delete('status-pages/{statusPage}', [StatusPageController::class, 'destroy'])->middleware(['can:update,statusPage', 'throttle:status-page-writes'])->name('status-pages.destroy');

    Route::get('maintenance-windows', [MaintenanceWindowController::class, 'index'])->name('maintenance-windows.index');
    Route::post('maintenance-windows', [MaintenanceWindowController::class, 'store'])->middleware(['can:manage-resources', 'throttle:maintenance-writes'])->name('maintenance-windows.store');
    Route::get('maintenance-windows/{maintenanceWindow}', [MaintenanceWindowController::class, 'show'])->middleware('can:view,maintenanceWindow')->name('maintenance-windows.show');
    Route::match(['put', 'patch'], 'maintenance-windows/{maintenanceWindow}', [MaintenanceWindowController::class, 'update'])->middleware(['can:update,maintenanceWindow', 'throttle:maintenance-writes'])->name('maintenance-windows.update');
    Route::delete('maintenance-windows/{maintenanceWindow}', [MaintenanceWindowController::class, 'destroy'])->middleware(['can:update,maintenanceWindow', 'throttle:maintenance-writes'])->name('maintenance-windows.destroy');

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
            Route::post('heartbeat-token', MonitorHeartbeatTokenController::class)->name('heartbeat-token');
            Route::post('badge', [MonitorBadgeTokenController::class, 'store'])->name('badge.store');
            Route::delete('badge', [MonitorBadgeTokenController::class, 'destroy'])->name('badge.destroy');
        });
    });
});

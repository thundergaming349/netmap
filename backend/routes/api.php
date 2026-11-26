<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Api\V1\CameraController;
use App\Http\Controllers\Api\V1\ConflictEventController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\IpMapController;
use App\Http\Controllers\Api\V1\IpReservationController;
use App\Http\Controllers\Api\V1\LogoutController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MikrotikActionController;
use App\Http\Controllers\Api\V1\RevokeUserSessionsController;
use App\Http\Controllers\Api\V1\SegmentController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\TopologyController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('me', MeController::class)->name('me');
    Route::post('auth/logout', LogoutController::class)->name('auth.logout');
    Route::get('stream-auth', [CameraController::class, 'streamAuth'])
        ->middleware('can:'.Permission::ViewCctv->value)
        ->name('stream-auth');

    // Network views (Ringkasan, Peta IP, Route, Perangkat, Konflik)
    Route::middleware('can:'.Permission::ViewNetwork->value)->group(function (): void {
        Route::get('dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');

        Route::get('segments', [SegmentController::class, 'index'])->name('segments.index');
        Route::get('segments/{segment}', [SegmentController::class, 'show'])->name('segments.show');
        Route::get('segments/{segment}/ip-map', [IpMapController::class, 'show'])->name('segments.ip-map');
        Route::get('segments/{segment}/next-free-ip', [IpMapController::class, 'nextFreeIp'])->name('segments.next-free-ip');
        Route::get('ip-check', [IpMapController::class, 'check'])->name('ip-check');

        Route::get('ip-reservations', [IpReservationController::class, 'index'])->name('ip-reservations.index');

        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::get('devices/export', [DeviceController::class, 'export'])->name('devices.export');
        Route::get('devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
        Route::get('devices/{device}/trace', [DeviceController::class, 'trace'])->name('devices.trace');

        Route::get('topology', [TopologyController::class, 'topology'])->name('topology');
        Route::get('routes', [TopologyController::class, 'routes'])->name('routes.index');

        Route::get('conflict-events', [ConflictEventController::class, 'index'])->name('conflict-events.index');
        Route::get('conflict-events/{conflictEvent}', [ConflictEventController::class, 'show'])->name('conflict-events.show');

        // Manual sync & ARP scan are non-destructive read/probe triggers
        Route::post('mikrotik/sync', [MikrotikActionController::class, 'sync'])->name('mikrotik.sync');
        Route::post('mikrotik/scan', [MikrotikActionController::class, 'scan'])->name('mikrotik.scan');
    });

    // Manage devices (Admin + Technician)
    Route::middleware('can:'.Permission::ManageDevices->value)->group(function (): void {
        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::post('devices/import', [DeviceController::class, 'import'])->name('devices.import');
        Route::put('devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
        Route::patch('devices/{device}', [DeviceController::class, 'update']);
        Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    });

    // Manage IP reservations (Admin + Technician)
    Route::middleware('can:'.Permission::ManageReservations->value)->group(function (): void {
        Route::post('ip-reservations', [IpReservationController::class, 'store'])->name('ip-reservations.store');
        Route::delete('ip-reservations/{ipReservation}', [IpReservationController::class, 'destroy'])->name('ip-reservations.destroy');
    });

    // Resolve conflicts (Admin + Technician)
    Route::middleware('can:'.Permission::ResolveConflicts->value)->group(function (): void {
        Route::post('conflict-events/{conflictEvent}/resolve', [ConflictEventController::class, 'resolve'])->name('conflict-events.resolve');
    });

    // Live CCTV (Admin + Technician + CCTV Viewer)
    Route::middleware('can:'.Permission::ViewCctv->value)->group(function (): void {
        Route::get('cameras', [CameraController::class, 'index'])->name('cameras.index');
        Route::get('cameras/{camera}/stream', [CameraController::class, 'stream'])->name('cameras.stream');
        Route::get('cameras/{camera}/recordings', [CameraController::class, 'recordings'])->name('cameras.recordings');
        Route::get('recordings/{recording}/download', [CameraController::class, 'downloadRecording'])->name('recordings.download');
        Route::post('cameras/{camera}/snapshot', [CameraController::class, 'snapshot'])->name('cameras.snapshot');
        Route::post('cameras/{camera}/ping', [CameraController::class, 'ping'])->name('cameras.ping');
        Route::get('nvrs', [CameraController::class, 'nvrs'])->name('nvrs.index');
    });

    // Write actions to MikroTik (Admin + MIKROTIK_WRITE_ENABLED=true)
    Route::middleware(['can:'.Permission::MikrotikWrite->value, 'write.enabled'])->prefix('mikrotik')->name('mikrotik.')->group(function (): void {
        Route::post('leases/make-static', [MikrotikActionController::class, 'makeLeaseStatic'])->name('leases.make-static');
        Route::post('arp', [MikrotikActionController::class, 'addArp'])->name('arp.store');
        Route::post('mac-blocks', [MikrotikActionController::class, 'blockMac'])->name('mac-blocks.store');
        Route::delete('mac-blocks/{macBlock}', [MikrotikActionController::class, 'unblockMac'])->name('mac-blocks.destroy');
        Route::post('poe/{interface}/restart', [MikrotikActionController::class, 'restartPoe'])->name('poe.restart');
    });

    // Settings, Audit Log & Backups (Admin)
    Route::middleware('can:'.Permission::ManageSettings->value)->group(function (): void {
        Route::patch('segments/{segment}', [SegmentController::class, 'update'])->name('segments.update');
        Route::get('settings/{group}', [SettingController::class, 'show'])->name('settings.show');
        Route::put('settings/{group}', [SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/mikrotik/test', [SettingController::class, 'testMikrotik'])->name('settings.mikrotik.test');
        Route::post('settings/notifications/test', [SettingController::class, 'testNotification'])->name('settings.notifications.test');
        Route::get('audit-logs', [SettingController::class, 'auditLogs'])->name('audit-logs.index');
        Route::get('backups', [SettingController::class, 'backups'])->name('backups.index');
        Route::post('backups', [SettingController::class, 'createBackup'])->name('backups.store');
        Route::get('backups/{backup}/download', [SettingController::class, 'downloadBackup'])->name('backups.download');
    });

    // User allowlist management (Admin)
    Route::middleware('can:'.Permission::ManageUsers->value)->group(function (): void {
        Route::apiResource('users', UserController::class)->except('show');
        Route::post('users/{user}/revoke-sessions', RevokeUserSessionsController::class)->name('users.revoke-sessions');
    });
});

<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\EmergencyLoginController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Webhook\MikrotikWebhookController;
use Illuminate\Support\Facades\Route;

// The UI is the Next.js app. Laravel only serves the sign-in redirects and MikroTik webhooks here.
Route::prefix('auth')->name('auth.')->middleware('throttle:10,1')->group(function (): void {
    Route::get('google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
    Route::get('emergency/{nonce}', EmergencyLoginController::class)->middleware('signed:relative')->name('emergency');
});

Route::prefix('webhook/mikrotik')->name('webhook.mikrotik.')->middleware(['webhook.verify', 'throttle:120,1'])->group(function (): void {
    Route::match(['get', 'post'], 'lease', [MikrotikWebhookController::class, 'lease'])->name('lease');
    Route::match(['get', 'post'], 'netwatch', [MikrotikWebhookController::class, 'netwatch'])->name('netwatch');
});

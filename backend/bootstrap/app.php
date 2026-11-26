<?php

declare(strict_types=1);

use App\Exceptions\RecordingUnavailableException;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureWriteActionsEnabled;
use App\Http\Middleware\VerifyWebhookSource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'auth:sanctum']])
    ->withMiddleware(function (Middleware $middleware): void {
        // Requests arrive through Caddy (production) or the Next dev proxy.
        $middleware->trustProxies(at: '*');
        $middleware->statefulApi();
        $middleware->validateCsrfTokens(except: ['webhook/*']);
        $middleware->redirectGuestsTo(fn () => '/login');
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'write.enabled' => EnsureWriteActionsEnabled::class,
            'webhook.verify' => VerifyWebhookSource::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $json = fn (string $message, string $code, int $status, array $extra = []) => response()->json(
            ['message' => $message, 'code' => $code, ...$extra],
            $status,
        );

        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->expectsJson() || $request->is('api/*')
            ? $json('Silakan masuk dulu.', 'unauthenticated', 401)
            : null);

        $exceptions->render(fn (AccessDeniedHttpException $e, Request $request) => $request->is('api/*')
            ? $json('Kamu tidak punya akses untuk aksi ini.', 'forbidden', 403)
            : null);

        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? $json('Data tidak ditemukan.', 'not_found', 404)
            : null);

        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $request->is('api/*')
            ? $json('Terlalu banyak permintaan. Coba lagi sebentar.', 'too_many_requests', 429)
            : null);

        $exceptions->render(fn (RecordingUnavailableException $e, Request $request) => $request->is('api/*') || $request->expectsJson()
            ? $json($e->getMessage(), 'stream_unavailable', 503)
            : null);

        $exceptions->render(fn (ValidationException $e, Request $request) => $request->is('api/*')
            ? $json(
                count($e->errors()) > 1 ? 'Periksa '.count($e->errors()).' isian yang salah.' : (string) collect($e->errors())->flatten()->first(),
                'validation_failed',
                422,
                ['errors' => $e->errors()],
            )
            : null);
    })->create();

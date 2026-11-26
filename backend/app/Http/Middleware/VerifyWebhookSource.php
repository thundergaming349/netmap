<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Router;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyWebhookSource
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->query('token', $request->input('token', ''));
        $router = Router::query()->first();

        if ($router === null || $router->webhook_token === null || $token === '') {
            return response()->json(['message' => 'Token webhook tidak valid.', 'code' => 'forbidden'], 403);
        }

        if (! hash_equals($router->webhook_token, $token)) {
            return response()->json(['message' => 'Token webhook tidak cocok.', 'code' => 'forbidden'], 403);
        }

        $clientIp = (string) $request->ip();
        $allowedIps = array_filter([$router->host, '127.0.0.1', '::1']);

        if (! in_array($clientIp, $allowedIps, true)) {
            return response()->json(['message' => 'IP pengirim webhook tidak diizinkan.', 'code' => 'forbidden'], 403);
        }

        return $next($request);
    }
}

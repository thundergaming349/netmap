<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\WriteActionsDisabledException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureWriteActionsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('netmap.mikrotik_write_enabled')) {
            throw new WriteActionsDisabledException;
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RequireStatefulSpaSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'A stateful SPA session is required.');
        }

        return $next($request);
    }
}

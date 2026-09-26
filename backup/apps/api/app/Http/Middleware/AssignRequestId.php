<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RequestId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

final class AssignRequestId
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }

        $requestId = RequestId::getOrCreate($request);
        Context::add('request_id', $requestId);

        return RequestId::attach($next($request), $requestId);
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Exceptions;

use App\Support\RequestId;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class DashboardPersistenceFailure extends HttpException
{
    private function __construct()
    {
        parent::__construct(Response::HTTP_INTERNAL_SERVER_ERROR, 'Dashboard read failed.');
    }

    public static function fromQueryException(QueryException $exception): self
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        Log::error('Dashboard read failed.', [
            'operation' => 'dashboard.read',
            'sql_state' => is_string($sqlState) && preg_match('/^[A-Z0-9]{5}$/D', $sqlState) === 1
                ? $sqlState
                : 'unknown',
            'exception_type' => QueryException::class,
            'request_id' => RequestId::getOrCreate(request()),
        ]);

        return new self;
    }
}

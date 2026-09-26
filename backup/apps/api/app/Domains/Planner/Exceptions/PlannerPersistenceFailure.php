<?php

declare(strict_types=1);

namespace App\Domains\Planner\Exceptions;

use App\Support\RequestId;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PlannerPersistenceFailure extends HttpException
{
    /** @var list<string> */
    private const OPERATIONS = [
        'task.read',
        'task.list',
        'task.create',
        'task.update',
        'task.status',
        'task.archive',
        'task.restore',
        'task.delete',
        'focus.read',
        'focus.list',
        'focus.create',
        'focus.update',
        'focus.delete',
        'planner.agenda',
        'planner.weekly',
    ];

    private function __construct()
    {
        parent::__construct(Response::HTTP_INTERNAL_SERVER_ERROR, 'Planner persistence failed.');
    }

    public static function fromQueryException(QueryException $exception, string $operation): self
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        Log::error('Planner persistence failed.', [
            'operation' => in_array($operation, self::OPERATIONS, true) ? $operation : 'unknown',
            'sql_state' => is_string($sqlState) && preg_match('/^[A-Z0-9]{5}$/D', $sqlState) === 1
                ? $sqlState
                : 'unknown',
            'exception_type' => QueryException::class,
            'request_id' => RequestId::getOrCreate(request()),
        ]);

        return new self;
    }
}

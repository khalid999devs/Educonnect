<?php

declare(strict_types=1);

namespace App\Domains\Courses\Exceptions;

use App\Support\RequestId;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AcademicPersistenceFailure extends HttpException
{
    /** @var list<string> */
    private const OPERATIONS = [
        'term.read',
        'term.list',
        'term.create',
        'term.update',
        'term.delete',
        'course.read',
        'course.list',
        'course.create',
        'course.update',
        'course.archive',
        'course.restore',
        'course.delete',
    ];

    private function __construct()
    {
        parent::__construct(Response::HTTP_INTERNAL_SERVER_ERROR, 'Academic persistence failed.');
    }

    public static function fromQueryException(QueryException $exception, string $operation): self
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        Log::error('Academic persistence failed.', [
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

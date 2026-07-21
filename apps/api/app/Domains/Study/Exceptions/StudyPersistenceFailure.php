<?php

declare(strict_types=1);

namespace App\Domains\Study\Exceptions;

use App\Support\RequestId;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The Study domain's single persistence-failure boundary. Nothing about the
 * driver, the SQL, or the failing row reaches the client or the log.
 *
 * OPERATIONS is an allowlist, not documentation: an operation label that is not
 * listed is recorded as "unknown", so every new call site must add its label
 * here in the same change.
 */
final class StudyPersistenceFailure extends HttpException
{
    /** @var list<string> */
    private const OPERATIONS = [
        'study.generation.request',
        'study.artifact.read',
        'study.artifact.list',
    ];

    private function __construct()
    {
        parent::__construct(Response::HTTP_INTERNAL_SERVER_ERROR, 'Study persistence failed.');
    }

    public static function fromQueryException(QueryException $exception, string $operation): self
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        Log::error('Study persistence failed.', [
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

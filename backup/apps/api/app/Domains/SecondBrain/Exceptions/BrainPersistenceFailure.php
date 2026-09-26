<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Exceptions;

use App\Support\RequestId;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class BrainPersistenceFailure extends HttpException
{
    /** @var list<string> */
    private const OPERATIONS = [
        'collection.read',
        'collection.list',
        'collection.create',
        'collection.update',
        'collection.delete',
        'knowledge.read',
        'knowledge.list',
        'knowledge.create',
        'knowledge.update',
        'knowledge.delete',
        'knowledge.note.create',
        'knowledge.note.update',
        'knowledge.note.delete',
        'knowledge.tags.sync',
        'knowledge.collections.sync',
        'knowledge.link.create',
        'knowledge.link.delete',
        'brain.item.save',
        'brain.item.unsave',
        'research.read',
        'research.list',
        'research.create',
        'research.update',
        'research.delete',
        'research.source.attach',
        'research.source.update',
        'research.source.detach',
    ];

    private function __construct()
    {
        parent::__construct(Response::HTTP_INTERNAL_SERVER_ERROR, 'Second Brain persistence failed.');
    }

    public static function fromQueryException(QueryException $exception, string $operation): self
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        Log::error('Second Brain persistence failed.', [
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

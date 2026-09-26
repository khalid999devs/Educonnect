<?php

declare(strict_types=1);

namespace App\Domains\Community\Exceptions;

use App\Support\RequestId;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CommunityPersistenceFailure extends HttpException
{
    /** @var list<string> */
    private const OPERATIONS = [
        'community.list',
        'community.read',
        'community.membership.join',
        'community.membership.leave',
        'feed.list',
        'post.list',
        'post.read',
        'post.create',
        'post.update',
        'post.delete',
        'comment.list',
        'comment.create',
        'comment.delete',
        'report.create',
        'moderation.list',
        'moderation.resolve',
    ];

    private function __construct()
    {
        parent::__construct(Response::HTTP_INTERNAL_SERVER_ERROR, 'Community persistence failed.');
    }

    public static function fromQueryException(QueryException $exception, string $operation): self
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        Log::error('Community persistence failed.', [
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

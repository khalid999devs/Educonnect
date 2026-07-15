<?php

declare(strict_types=1);

namespace App\Domains\Resources\Exceptions;

use App\Support\RequestId;
use Illuminate\Support\Facades\Log;
use League\Flysystem\FilesystemException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

final class ResourceStorageFailure extends HttpException
{
    /** @var list<string> */
    private const OPERATIONS = [
        'upload.sign',
        'object.exists',
        'upload.size',
        'upload.read',
        'upload.copy',
        'download.sign',
        'object.delete',
    ];

    private function __construct()
    {
        parent::__construct(Response::HTTP_SERVICE_UNAVAILABLE, 'Resource storage is temporarily unavailable.');
    }

    public static function fromThrowable(Throwable $exception, string $operation): self
    {
        Log::error('Resource storage operation failed.', [
            'operation' => in_array($operation, self::OPERATIONS, true) ? $operation : 'unknown',
            'exception_type' => self::safeExceptionType($exception),
            'request_id' => RequestId::getOrCreate(request()),
        ]);

        return new self;
    }

    private static function safeExceptionType(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof FilesystemException => 'filesystem',
            $exception instanceof RuntimeException => 'runtime',
            default => 'unknown',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequestId
{
    public const ATTRIBUTE = 'educonnect.request_id';

    public const HEADER = 'X-Request-ID';

    public const PATTERN = '/^req_[a-f0-9]{32}$/D';

    public static function getOrCreate(Request $request): string
    {
        $existing = $request->attributes->get(self::ATTRIBUTE);

        if (is_string($existing) && preg_match(self::PATTERN, $existing) === 1) {
            return $existing;
        }

        $requestId = 'req_'.bin2hex(random_bytes(16));
        $request->attributes->set(self::ATTRIBUTE, $requestId);

        return $requestId;
    }

    public static function attach(Response $response, string $requestId): Response
    {
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}

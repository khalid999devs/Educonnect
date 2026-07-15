<?php

declare(strict_types=1);

namespace App\Domains\Resources\Support;

use Aws\Signature\S3SignatureV4;

/**
 * AWS's generic presigner deliberately omits entity headers for proxy
 * compatibility. Resource uploads need the opposite invariant: the object
 * store must reject a body whose browser-generated length or declared type
 * differs from the persisted upload contract.
 */
final class StrictS3SignatureV4 extends S3SignatureV4
{
    /** @return array<string, bool> */
    protected function getHeaderBlacklist(): array
    {
        $headers = parent::getHeaderBlacklist();
        unset($headers['content-length']);

        return $headers;
    }

    /** @return array<string, bool> */
    protected function getPresignHeaderDenyList(): array
    {
        $headers = parent::getPresignHeaderDenyList();
        unset($headers['content-type']);

        return $headers;
    }
}

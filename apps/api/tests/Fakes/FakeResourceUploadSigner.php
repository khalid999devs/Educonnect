<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domains\Resources\Contracts\ResourceUploadSigner;
use App\Domains\Resources\Data\UploadPutGrant;
use Carbon\CarbonImmutable;
use Throwable;

final readonly class FakeResourceUploadSigner implements ResourceUploadSigner
{
    public function __construct(
        private string $url = 'https://objects.example.test/private-upload',
        private ?Throwable $failure = null,
    ) {}

    public function sign(
        string $key,
        CarbonImmutable $expiresAt,
        string $mimeType,
        int $size,
    ): UploadPutGrant {
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        return new UploadPutGrant($this->url, [
            'Content-Type' => $mimeType,
        ]);
    }
}

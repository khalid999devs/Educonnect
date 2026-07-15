<?php

declare(strict_types=1);

namespace App\Domains\Resources\Data;

final readonly class StoragePolicySnapshot
{
    public const PROVIDER_AWS = 'aws';

    public const PROVIDER_R2 = 'r2';

    public const PROVIDER_CUSTOM = 'custom';

    /**
     * @param  array<string, mixed>  $lifecycle
     * @param  array<string, mixed>  $cors
     * @param  array<string, mixed>|null  $versioning
     * @param  array<string, mixed>|null  $publicAccessBlock
     * @param  array<string, mixed>|null  $policyStatus
     */
    public function __construct(
        public string $provider,
        public int $maxLifecycleDays,
        public string $uploadPrefix,
        public string $objectPrefix,
        public string $frontendOrigin,
        public array $lifecycle,
        public array $cors,
        public ?array $versioning = null,
        public ?array $publicAccessBlock = null,
        public ?array $policyStatus = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Domains\Resources\Data\StoragePolicyCheck;
use App\Domains\Resources\Data\StoragePolicySnapshot;
use App\Domains\Resources\Support\ResourceStoragePolicyVerifier;
use PHPUnit\Framework\TestCase as BaseTestCase;

final class ResourceStoragePolicyVerifierTest extends BaseTestCase
{
    private ResourceStoragePolicyVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = new ResourceStoragePolicyVerifier;
    }

    public function test_aws_policy_passes_with_bounded_staging_lifecycle_exact_cors_and_versioning_disabled(): void
    {
        $report = $this->verifier->verify($this->snapshot());

        self::assertTrue($report->passes());
        self::assertSame(
            [
                StoragePolicyCheck::PASS,
                StoragePolicyCheck::PASS,
                StoragePolicyCheck::PASS,
                StoragePolicyCheck::PASS,
                StoragePolicyCheck::PASS,
            ],
            array_map(static fn (StoragePolicyCheck $check): string => $check->status, $report->checks),
        );
    }

    public function test_lifecycle_must_be_enabled_unconstrained_cover_the_full_prefix_and_expire_in_time(): void
    {
        foreach ([
            'disabled' => [[
                'Status' => 'Disabled',
                'Filter' => ['Prefix' => $this->uploadPrefix()],
                'Expiration' => ['Days' => 1],
            ]],
            'narrow prefix' => [[
                'Status' => 'Enabled',
                'Filter' => ['Prefix' => $this->uploadPrefix().'subset/'],
                'Expiration' => ['Days' => 1],
            ]],
            'tag constrained' => [[
                'Status' => 'Enabled',
                'Filter' => ['And' => [
                    'Prefix' => $this->uploadPrefix(),
                    'Tags' => [['Key' => 'temporary', 'Value' => 'true']],
                ]],
                'Expiration' => ['Days' => 1],
            ]],
            'too late' => [[
                'Status' => 'Enabled',
                'Filter' => ['Prefix' => $this->uploadPrefix()],
                'Expiration' => ['Days' => 2],
            ]],
        ] as $rules) {
            $report = $this->verifier->verify($this->snapshot(lifecycle: ['Rules' => $rules]));

            self::assertFalse($report->passes());
            self::assertSame(StoragePolicyCheck::FAIL, $report->checks[0]->status);
        }
    }

    public function test_staging_expiration_must_be_exact_and_must_not_cover_durable_objects(): void
    {
        $broadOnly = $this->verifier->verify($this->snapshot(lifecycle: ['Rules' => [[
            'Status' => 'Enabled',
            'Filter' => [],
            'Expiration' => ['Days' => 1],
        ]]]));

        self::assertFalse($broadOnly->passes());
        self::assertSame(StoragePolicyCheck::FAIL, $broadOnly->checks[0]->status);
        self::assertSame(StoragePolicyCheck::FAIL, $broadOnly->checks[1]->status);

        $safeAndBroad = $this->validLifecycle();
        $safeAndBroad['Rules'][] = [
            'Status' => 'Enabled',
            'Filter' => ['Prefix' => 'educonnect/testing/resources/v1/'],
            'Expiration' => ['Days' => 1],
        ];
        $report = $this->verifier->verify($this->snapshot(lifecycle: $safeAndBroad));

        self::assertFalse($report->passes());
        self::assertSame(StoragePolicyCheck::PASS, $report->checks[0]->status);
        self::assertSame(StoragePolicyCheck::FAIL, $report->checks[1]->status);
        self::assertStringContainsString('durable resource objects', $report->checks[1]->message);
    }

    public function test_delete_marker_cleanup_does_not_count_as_durable_current_object_expiration(): void
    {
        $lifecycle = $this->validLifecycle();
        $lifecycle['Rules'][] = [
            'Status' => 'Enabled',
            'Filter' => ['Prefix' => $this->objectPrefix()],
            'Expiration' => ['ExpiredObjectDeleteMarker' => true],
        ];

        $report = $this->verifier->verify($this->snapshot(lifecycle: $lifecycle));

        self::assertTrue($report->passes());
        self::assertSame(StoragePolicyCheck::PASS, $report->checks[1]->status);
    }

    public function test_cors_rejects_wildcard_or_extra_origins_and_requires_put_with_content_type(): void
    {
        foreach ([
            [['AllowedOrigins' => ['*'], 'AllowedMethods' => ['PUT'], 'AllowedHeaders' => ['*']]],
            [[
                'AllowedOrigins' => ['https://web.educonnect.test', 'https://other.example'],
                'AllowedMethods' => ['PUT'],
                'AllowedHeaders' => ['Content-Type'],
            ]],
            [[
                'AllowedOrigins' => ['https://web.educonnect.test'],
                'AllowedMethods' => ['GET'],
                'AllowedHeaders' => ['Content-Type'],
            ]],
            [[
                'AllowedOrigins' => ['https://web.educonnect.test'],
                'AllowedMethods' => ['PUT'],
                'AllowedHeaders' => ['X-Custom'],
            ]],
        ] as $rules) {
            $report = $this->verifier->verify($this->snapshot(cors: ['CORSRules' => $rules]));

            self::assertFalse($report->passes());
            self::assertSame(StoragePolicyCheck::FAIL, $report->checks[2]->status);
        }
    }

    public function test_enabled_or_suspended_aws_versioning_requires_noncurrent_and_delete_marker_cleanup_for_both_prefixes(): void
    {
        $unsafe = $this->verifier->verify($this->snapshot(versioning: ['Status' => 'Enabled']));

        self::assertFalse($unsafe->passes());
        self::assertSame(StoragePolicyCheck::FAIL, $unsafe->checks[3]->status);

        $lifecycle = $this->validLifecycle();
        $lifecycle['Rules'][] = $this->versionCleanupRule($this->uploadPrefix());
        $lifecycle['Rules'][] = $this->versionCleanupRule($this->objectPrefix());

        foreach (['Enabled', 'Suspended'] as $status) {
            $safe = $this->verifier->verify($this->snapshot(
                lifecycle: $lifecycle,
                versioning: ['Status' => $status],
            ));

            self::assertTrue($safe->passes());
            self::assertSame(StoragePolicyCheck::PASS, $safe->checks[3]->status);
        }
    }

    public function test_aws_public_access_requires_all_four_blocks_and_a_nonpublic_policy(): void
    {
        $block = $this->validPublicAccessBlock();
        $block['PublicAccessBlockConfiguration']['RestrictPublicBuckets'] = false;

        $missingBlock = $this->verifier->verify($this->snapshot(publicAccessBlock: $block));
        $publicPolicy = $this->verifier->verify($this->snapshot(
            policyStatus: ['PolicyStatus' => ['IsPublic' => true]],
        ));

        self::assertFalse($missingBlock->passes());
        self::assertFalse($publicPolicy->passes());
        self::assertSame(StoragePolicyCheck::FAIL, $missingBlock->checks[4]->status);
        self::assertSame(StoragePolicyCheck::FAIL, $publicPolicy->checks[4]->status);
    }

    public function test_r2_requires_lifecycle_and_cors_but_reports_unsupported_controls_as_manual(): void
    {
        $report = $this->verifier->verify($this->snapshot(
            provider: StoragePolicySnapshot::PROVIDER_R2,
            versioning: null,
            publicAccessBlock: null,
            policyStatus: null,
        ));

        self::assertFalse($report->passes());
        self::assertSame(StoragePolicyCheck::MANUAL, $report->checks[3]->status);
        self::assertSame(StoragePolicyCheck::MANUAL, $report->checks[4]->status);
        self::assertStringContainsString('Cloudflare R2', $report->checks[3]->message);
        self::assertStringContainsString('Cloudflare R2', $report->checks[4]->message);
    }

    public function test_unknown_custom_provider_never_receives_false_automatic_verification(): void
    {
        $report = $this->verifier->verify($this->snapshot(
            provider: StoragePolicySnapshot::PROVIDER_CUSTOM,
            versioning: null,
            publicAccessBlock: null,
            policyStatus: null,
        ));

        self::assertFalse($report->passes());
        self::assertSame(StoragePolicyCheck::FAIL, $report->checks[3]->status);
    }

    /**
     * @param  array<string, mixed>|null  $lifecycle
     * @param  array<string, mixed>|null  $cors
     * @param  array<string, mixed>|null  $versioning
     * @param  array<string, mixed>|null  $publicAccessBlock
     * @param  array<string, mixed>|null  $policyStatus
     */
    private function snapshot(
        string $provider = StoragePolicySnapshot::PROVIDER_AWS,
        ?array $lifecycle = null,
        ?array $cors = null,
        ?array $versioning = [],
        ?array $publicAccessBlock = null,
        ?array $policyStatus = null,
    ): StoragePolicySnapshot {
        return new StoragePolicySnapshot(
            provider: $provider,
            maxLifecycleDays: 1,
            uploadPrefix: $this->uploadPrefix(),
            objectPrefix: $this->objectPrefix(),
            frontendOrigin: 'https://web.educonnect.test',
            lifecycle: $lifecycle ?? $this->validLifecycle(),
            cors: $cors ?? $this->validCors(),
            versioning: $versioning,
            publicAccessBlock: $publicAccessBlock ?? $this->validPublicAccessBlock(),
            policyStatus: $policyStatus ?? ['PolicyStatus' => ['IsPublic' => false]],
        );
    }

    /** @return array<string, mixed> */
    private function validLifecycle(): array
    {
        return ['Rules' => [[
            'Status' => 'Enabled',
            'Filter' => ['Prefix' => $this->uploadPrefix()],
            'Expiration' => ['Days' => 1],
        ]]];
    }

    /** @return array<string, mixed> */
    private function validCors(): array
    {
        return ['CORSRules' => [[
            'AllowedOrigins' => ['https://web.educonnect.test'],
            'AllowedMethods' => ['PUT'],
            'AllowedHeaders' => ['Content-Type'],
        ]]];
    }

    /** @return array<string, mixed> */
    private function validPublicAccessBlock(): array
    {
        return ['PublicAccessBlockConfiguration' => [
            'BlockPublicAcls' => true,
            'IgnorePublicAcls' => true,
            'BlockPublicPolicy' => true,
            'RestrictPublicBuckets' => true,
        ]];
    }

    /** @return array<string, mixed> */
    private function versionCleanupRule(string $prefix): array
    {
        return [
            'Status' => 'Enabled',
            'Filter' => ['Prefix' => $prefix],
            'NoncurrentVersionExpiration' => ['NoncurrentDays' => 1],
            'Expiration' => ['ExpiredObjectDeleteMarker' => true],
        ];
    }

    private function uploadPrefix(): string
    {
        return 'educonnect/testing/resources/v1/uploads/';
    }

    private function objectPrefix(): string
    {
        return 'educonnect/testing/resources/v1/objects/';
    }
}

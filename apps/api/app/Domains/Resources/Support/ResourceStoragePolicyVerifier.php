<?php

declare(strict_types=1);

namespace App\Domains\Resources\Support;

use App\Domains\Resources\Data\StoragePolicyCheck;
use App\Domains\Resources\Data\StoragePolicyReport;
use App\Domains\Resources\Data\StoragePolicySnapshot;

final class ResourceStoragePolicyVerifier
{
    public function verify(StoragePolicySnapshot $snapshot): StoragePolicyReport
    {
        $checks = [
            $this->lifecycleCheck($snapshot),
            $this->durableObjectRetentionCheck($snapshot),
            $this->corsCheck($snapshot),
        ];

        if ($snapshot->provider === StoragePolicySnapshot::PROVIDER_AWS) {
            $checks[] = $this->versioningCheck($snapshot);
            $checks[] = $this->publicAccessCheck($snapshot);
        } elseif ($snapshot->provider === StoragePolicySnapshot::PROVIDER_R2) {
            $checks[] = new StoragePolicyCheck(
                StoragePolicyCheck::MANUAL,
                'Cloudflare R2 versioning and retained-version cleanup require a provider-dashboard review.',
            );
            $checks[] = new StoragePolicyCheck(
                StoragePolicyCheck::MANUAL,
                'Cloudflare R2 public-bucket exposure requires a provider-dashboard review.',
            );
        } else {
            $checks[] = new StoragePolicyCheck(
                StoragePolicyCheck::FAIL,
                'The custom S3 provider is not supported for automatic versioning and public-access verification.',
            );
        }

        return new StoragePolicyReport($checks);
    }

    private function lifecycleCheck(StoragePolicySnapshot $snapshot): StoragePolicyCheck
    {
        foreach ($this->rules($snapshot->lifecycle) as $rule) {
            $expiration = $rule['Expiration'] ?? null;
            $days = is_array($expiration) ? ($expiration['Days'] ?? null) : null;

            if (($rule['Status'] ?? null) === 'Enabled'
                && $this->unconstrainedPrefix($rule) === $snapshot->uploadPrefix
                && is_int($days)
                && $days >= 1
                && $days <= $snapshot->maxLifecycleDays) {
                return new StoragePolicyCheck(
                    StoragePolicyCheck::PASS,
                    'Enabled lifecycle expiration covers every staging upload within the configured maximum.',
                );
            }
        }

        return new StoragePolicyCheck(
            StoragePolicyCheck::FAIL,
            'No enabled lifecycle rule expires every staging upload within the configured maximum.',
        );
    }

    private function durableObjectRetentionCheck(StoragePolicySnapshot $snapshot): StoragePolicyCheck
    {
        foreach ($this->rules($snapshot->lifecycle) as $rule) {
            if (($rule['Status'] ?? null) !== 'Enabled' || ! $this->hasCurrentVersionExpiration($rule)) {
                continue;
            }

            $prefix = $this->filteredPrefix($rule);

            if ($prefix === null || $this->prefixesIntersect($prefix, $snapshot->objectPrefix)) {
                return new StoragePolicyCheck(
                    StoragePolicyCheck::FAIL,
                    'An enabled lifecycle expiration could delete durable resource objects.',
                );
            }
        }

        return new StoragePolicyCheck(
            StoragePolicyCheck::PASS,
            'No enabled lifecycle expiration targets durable resource objects.',
        );
    }

    private function corsCheck(StoragePolicySnapshot $snapshot): StoragePolicyCheck
    {
        $rules = $snapshot->cors['CORSRules'] ?? null;

        if (! is_array($rules)) {
            return $this->failedCorsCheck();
        }

        $allOriginsAreExact = true;
        $hasExactOrigin = false;
        $hasUsablePutRule = false;

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                $allOriginsAreExact = false;

                continue;
            }

            $origins = $this->strings($rule['AllowedOrigins'] ?? null);
            $methods = array_map('strtoupper', $this->strings($rule['AllowedMethods'] ?? null));
            $headers = array_map('strtolower', $this->strings($rule['AllowedHeaders'] ?? null));

            if ($origins === []
                || array_any($origins, static fn (string $origin): bool => $origin !== $snapshot->frontendOrigin)) {
                $allOriginsAreExact = false;
            }

            if (in_array($snapshot->frontendOrigin, $origins, true)) {
                $hasExactOrigin = true;

                if (in_array('PUT', $methods, true)
                    && (in_array('*', $headers, true) || in_array('content-type', $headers, true))) {
                    $hasUsablePutRule = true;
                }
            }
        }

        if ($allOriginsAreExact && $hasExactOrigin && $hasUsablePutRule) {
            return new StoragePolicyCheck(
                StoragePolicyCheck::PASS,
                'Bucket CORS permits the exact frontend origin to upload with Content-Type and has no other origins.',
            );
        }

        return $this->failedCorsCheck();
    }

    private function versioningCheck(StoragePolicySnapshot $snapshot): StoragePolicyCheck
    {
        $status = $snapshot->versioning['Status'] ?? null;

        if ($status === null || $status === '') {
            return new StoragePolicyCheck(
                StoragePolicyCheck::PASS,
                'Bucket versioning is disabled.',
            );
        }

        if (! in_array($status, ['Enabled', 'Suspended'], true)) {
            return $this->failedVersioningCheck();
        }

        foreach ([$snapshot->uploadPrefix, $snapshot->objectPrefix] as $prefix) {
            $hasNoncurrentExpiration = false;
            $hasDeleteMarkerCleanup = false;

            foreach ($this->rules($snapshot->lifecycle) as $rule) {
                if (! $this->enabledRuleCovers($rule, $prefix)) {
                    continue;
                }

                $noncurrentDays = $rule['NoncurrentVersionExpiration']['NoncurrentDays'] ?? null;

                if (is_int($noncurrentDays)
                    && $noncurrentDays >= 1
                    && $noncurrentDays <= $snapshot->maxLifecycleDays) {
                    $hasNoncurrentExpiration = true;
                }

                if (($rule['Expiration']['ExpiredObjectDeleteMarker'] ?? null) === true) {
                    $hasDeleteMarkerCleanup = true;
                }
            }

            if (! $hasNoncurrentExpiration || ! $hasDeleteMarkerCleanup) {
                return $this->failedVersioningCheck();
            }
        }

        return new StoragePolicyCheck(
            StoragePolicyCheck::PASS,
            'Versioned upload and object prefixes have bounded noncurrent-version and delete-marker cleanup.',
        );
    }

    private function publicAccessCheck(StoragePolicySnapshot $snapshot): StoragePolicyCheck
    {
        $configuration = $snapshot->publicAccessBlock['PublicAccessBlockConfiguration'] ?? null;
        $isPublic = $snapshot->policyStatus['PolicyStatus']['IsPublic'] ?? null;
        $required = ['BlockPublicAcls', 'IgnorePublicAcls', 'BlockPublicPolicy', 'RestrictPublicBuckets'];

        if (is_array($configuration)
            && array_all($required, static fn (string $key): bool => ($configuration[$key] ?? null) === true)
            && $isPublic === false) {
            return new StoragePolicyCheck(
                StoragePolicyCheck::PASS,
                'All bucket public-access blocks are enabled and the bucket policy is nonpublic.',
            );
        }

        return new StoragePolicyCheck(
            StoragePolicyCheck::FAIL,
            'Bucket public-access blocks or bucket-policy status do not prove private access.',
        );
    }

    /**
     * @param  array<string, mixed>  $lifecycle
     * @return list<array<string, mixed>>
     */
    private function rules(array $lifecycle): array
    {
        $rules = $lifecycle['Rules'] ?? null;

        if (! is_array($rules)) {
            return [];
        }

        return array_values(array_filter($rules, is_array(...)));
    }

    /** @param array<string, mixed> $rule */
    private function enabledRuleCovers(array $rule, string $targetPrefix): bool
    {
        if (($rule['Status'] ?? null) !== 'Enabled') {
            return false;
        }

        $prefix = $this->unconstrainedPrefix($rule);

        return $prefix !== null && str_starts_with($targetPrefix, $prefix);
    }

    /** @param array<string, mixed> $rule */
    private function unconstrainedPrefix(array $rule): ?string
    {
        if (array_key_exists('Filter', $rule)) {
            $filter = $rule['Filter'];

            if (! is_array($filter)) {
                return null;
            }

            if ($filter === []) {
                return '';
            }

            if (array_keys($filter) === ['Prefix'] && is_string($filter['Prefix'])) {
                return $filter['Prefix'];
            }

            $and = $filter['And'] ?? null;

            if (! is_array($and)) {
                return null;
            }

            $tags = $and['Tags'] ?? [];
            $extraKeys = array_diff(array_keys($and), ['Prefix', 'Tags']);

            return $extraKeys === []
                && $tags === []
                && is_string($and['Prefix'] ?? null)
                    ? $and['Prefix']
                    : null;
        }

        if (array_key_exists('Prefix', $rule)) {
            return is_string($rule['Prefix']) ? $rule['Prefix'] : null;
        }

        return '';
    }

    /** @param array<string, mixed> $rule */
    private function filteredPrefix(array $rule): ?string
    {
        if (array_key_exists('Filter', $rule)) {
            $filter = $rule['Filter'];

            if (! is_array($filter)) {
                return null;
            }

            if ($filter === []) {
                return '';
            }

            if (is_string($filter['Prefix'] ?? null)) {
                return $filter['Prefix'];
            }

            $and = $filter['And'] ?? null;

            if (is_array($and) && is_string($and['Prefix'] ?? null)) {
                return $and['Prefix'];
            }

            if (array_key_exists('Tag', $filter) || array_key_exists('Tags', $filter)) {
                return '';
            }

            return null;
        }

        if (array_key_exists('Prefix', $rule)) {
            return is_string($rule['Prefix']) ? $rule['Prefix'] : null;
        }

        return '';
    }

    /** @param array<string, mixed> $rule */
    private function hasCurrentVersionExpiration(array $rule): bool
    {
        if (! array_key_exists('Expiration', $rule)) {
            return false;
        }

        $expiration = $rule['Expiration'];

        return ! is_array($expiration)
            || array_key_exists('Days', $expiration)
            || array_key_exists('Date', $expiration);
    }

    private function prefixesIntersect(string $first, string $second): bool
    {
        return str_starts_with($first, $second) || str_starts_with($second, $first);
    }

    /** @return list<string> */
    private function strings(mixed $values): array
    {
        return is_array($values)
            ? array_values(array_filter($values, is_string(...)))
            : [];
    }

    private function failedCorsCheck(): StoragePolicyCheck
    {
        return new StoragePolicyCheck(
            StoragePolicyCheck::FAIL,
            'Bucket CORS must use only the exact frontend origin and allow PUT with Content-Type.',
        );
    }

    private function failedVersioningCheck(): StoragePolicyCheck
    {
        return new StoragePolicyCheck(
            StoragePolicyCheck::FAIL,
            'Versioning requires bounded noncurrent-version and delete-marker cleanup for uploads and objects.',
        );
    }
}

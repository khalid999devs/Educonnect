<?php

declare(strict_types=1);

namespace App\Domains\Intake\Contracts;

use App\Domains\Intake\AI\ClassificationRequest;

/**
 * Structured-output provider boundary. Implementations receive untrusted
 * extracted content and must return a raw suggestion payload that is always
 * validated against the versioned suggestion schema before persistence —
 * provider output is never trusted directly.
 */
interface AIProvider
{
    public function name(): string;

    public function model(): string;

    /** @return array<string, mixed> */
    public function classify(ClassificationRequest $request): array;
}

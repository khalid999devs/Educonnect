<?php

declare(strict_types=1);

namespace App\Domains\Intake\Contracts;

use App\Domains\Intake\Exceptions\IntakeAcquisitionFailure;

/**
 * Isolated extraction adapter boundary: implementations turn acquired raw
 * content into bounded plain text and must never execute or interpret the
 * content. Richer extractors (PDF, documents) plug in behind this contract.
 */
interface IntakeContentExtractor
{
    public function supports(string $contentType): bool;

    /**
     * @throws IntakeAcquisitionFailure
     */
    public function extract(string $rawContent, string $contentType): string;
}

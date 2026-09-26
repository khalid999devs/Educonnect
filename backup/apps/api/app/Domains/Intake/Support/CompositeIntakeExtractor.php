<?php

declare(strict_types=1);

namespace App\Domains\Intake\Support;

use App\Domains\Intake\Contracts\IntakeContentExtractor;
use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Exceptions\IntakeAcquisitionFailure;

/**
 * Dispatches extraction to the first registered adapter that supports the given
 * content type, so callers depend on one contract while plain text, PDF, and
 * image OCR each plug in behind it.
 */
final class CompositeIntakeExtractor implements IntakeContentExtractor
{
    /** @var list<IntakeContentExtractor> */
    private array $extractors;

    public function __construct(IntakeContentExtractor ...$extractors)
    {
        $this->extractors = array_values($extractors);
    }

    public function supports(string $contentType): bool
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($contentType)) {
                return true;
            }
        }

        return false;
    }

    public function extract(string $rawContent, string $contentType): string
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($contentType)) {
                return $extractor->extract($rawContent, $contentType);
            }
        }

        throw new IntakeAcquisitionFailure(
            IntakeFailureCode::UnsupportedContentType,
            'no extractor supports this content type',
        );
    }
}

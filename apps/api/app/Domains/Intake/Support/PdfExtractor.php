<?php

declare(strict_types=1);

namespace App\Domains\Intake\Support;

use App\Domains\Intake\Contracts\IntakeContentExtractor;
use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Exceptions\IntakeAcquisitionFailure;
use App\Domains\Intake\Support\Concerns\NormalizesExtractedText;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Extracts the embedded text layer of a PDF with a pure-PHP parser. Scanned,
 * image-only PDFs carry no text layer and surface as an honest "no readable
 * text" failure rather than a silent empty result.
 */
final class PdfExtractor implements IntakeContentExtractor
{
    use NormalizesExtractedText;

    private const SUPPORTED = ['application/pdf'];

    public function supports(string $contentType): bool
    {
        return in_array(strtolower($contentType), self::SUPPORTED, true);
    }

    public function extract(string $rawContent, string $contentType): string
    {
        if (! $this->supports($contentType)) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::UnsupportedContentType,
                'no extractor supports this content type',
            );
        }

        try {
            $text = (new Parser)->parseContent($rawContent)->getText();
        } catch (Throwable) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::ExtractionFailed,
                'the PDF could not be parsed for text',
            );
        }

        return $this->normalizeExtractedText($text);
    }
}

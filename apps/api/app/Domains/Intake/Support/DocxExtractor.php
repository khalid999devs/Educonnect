<?php

declare(strict_types=1);

namespace App\Domains\Intake\Support;

use App\Domains\Intake\Contracts\IntakeContentExtractor;
use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Exceptions\IntakeAcquisitionFailure;
use App\Domains\Intake\Support\Concerns\NormalizesExtractedText;
use App\Domains\Intake\Support\Concerns\ReadsOfficeArchive;
use DOMElement;
use ZipArchive;

/**
 * Extracts the visible text of a WordprocessingML document by walking
 * `word/document.xml` directly. A DOCX is a ZIP of XML parts, so the read is a
 * hardened unzip plus an in-order DOM walk - no document object model is built
 * and no third-party dependency is involved.
 *
 * Only the main document part is read. Headers, footers, footnotes, and comments
 * live in sibling parts and are deliberately out of scope: they are chrome, not
 * the material a student uploaded to be classified.
 */
final class DocxExtractor implements IntakeContentExtractor
{
    use NormalizesExtractedText;
    use ReadsOfficeArchive;

    private const SUPPORTED = ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    private const DOCUMENT_PART = 'word/document.xml';

    private const WORDPROCESSING_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

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

        $text = $this->readOfficeArchive($rawContent, function (ZipArchive $archive, array $names): string {
            if (! in_array(self::DOCUMENT_PART, $names, true)) {
                throw new IntakeAcquisitionFailure(
                    IntakeFailureCode::ExtractionFailed,
                    'the document is missing its main content part',
                );
            }

            $document = $this->readArchiveXml($archive, self::DOCUMENT_PART);

            if ($document === null) {
                throw new IntakeAcquisitionFailure(
                    IntakeFailureCode::ExtractionFailed,
                    'the document is missing its main content part',
                );
            }

            $collected = '';

            $this->walkElements(
                $document,
                function (DOMElement $element) use (&$collected): void {
                    if ($element->namespaceURI !== self::WORDPROCESSING_NS) {
                        return;
                    }

                    $collected .= match ($element->localName) {
                        't' => $element->textContent,
                        'tab' => "\t",
                        'br', 'cr' => "\n",
                        default => '',
                    };
                },
                function (DOMElement $element) use (&$collected): void {
                    // A paragraph close is the only reliable line boundary in
                    // WordprocessingML; runs inside it must not be split.
                    if ($element->namespaceURI === self::WORDPROCESSING_NS && $element->localName === 'p') {
                        $collected .= "\n";
                    }
                },
            );

            return $collected;
        });

        return $this->normalizeExtractedText($text);
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Intake\Support;

use App\Domains\Intake\Contracts\IntakeContentExtractor;
use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Exceptions\IntakeAcquisitionFailure;
use App\Domains\Intake\Support\Concerns\NormalizesExtractedText;
use App\Domains\Intake\Support\Concerns\ReadsOfficeArchive;
use DOMDocument;
use DOMElement;
use ZipArchive;

/**
 * Extracts slide text from a PresentationML package by walking each slide part
 * directly. A PPTX is a ZIP of XML parts, so the read is a hardened unzip plus
 * an in-order DOM walk over DrawingML text runs.
 *
 * Slides are read in true presentation order: `ppt/presentation.xml` lists each
 * slide as a `<p:sldId r:id="...">`, and `ppt/_rels/presentation.xml.rels` maps
 * that relationship id to its slide part. That order is authoritative because a
 * deck's file numbering need not match the order the slides are shown in. When
 * the presentation part or its relationships are missing or malformed, the read
 * falls back to NUMERIC filename order (`slide2.xml` before `slide10.xml`),
 * which is correct for the common case and never a lexical string sort.
 *
 * Speaker notes are emitted after the slides rather than interleaved. A notes
 * part is bound to its slide through the slide's relationship part, not through
 * its filename, so pairing `notesSlide7.xml` to `slide7.xml` would be a guess.
 * Appending them keeps every word without asserting an ordering we did not read.
 */
final class PptxExtractor implements IntakeContentExtractor
{
    use NormalizesExtractedText;
    use ReadsOfficeArchive;

    private const SUPPORTED = ['application/vnd.openxmlformats-officedocument.presentationml.presentation'];

    private const DRAWING_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const PRESENTATION_NS = 'http://schemas.openxmlformats.org/presentationml/2006/main';

    private const RELATIONSHIP_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const PACKAGE_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

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
            $slides = $this->orderedParts($names, '#^ppt/slides/slide(\d+)\.xml$#');

            if ($slides === []) {
                throw new IntakeAcquisitionFailure(
                    IntakeFailureCode::ExtractionFailed,
                    'the presentation contains no readable slides',
                );
            }

            $slides = $this->presentationOrderedSlides($archive, $names, $slides) ?? $slides;

            $notes = $this->orderedParts($names, '#^ppt/notesSlides/notesSlide(\d+)\.xml$#');
            $blocks = [];

            foreach ([...$slides, ...$notes] as $part) {
                $block = trim($this->partText($archive, $part));

                if ($block !== '') {
                    $blocks[] = $block;
                }
            }

            return implode("\n", $blocks);
        });

        return $this->normalizeExtractedText($text);
    }

    private function partText(ZipArchive $archive, string $entryName): string
    {
        $document = $this->readArchiveXml($archive, $entryName);

        if ($document === null) {
            return '';
        }

        $collected = '';

        $this->walkElements(
            $document,
            function (DOMElement $element) use (&$collected): void {
                if ($element->namespaceURI !== self::DRAWING_NS) {
                    return;
                }

                $collected .= match ($element->localName) {
                    't' => $element->textContent,
                    'br' => "\n",
                    default => '',
                };
            },
            function (DOMElement $element) use (&$collected): void {
                if ($element->namespaceURI === self::DRAWING_NS && $element->localName === 'p') {
                    $collected .= "\n";
                }
            },
        );

        return $collected;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function orderedParts(array $names, string $pattern): array
    {
        $indexed = [];

        foreach ($names as $name) {
            if (preg_match($pattern, $name, $matches) === 1) {
                $indexed[] = ['order' => (int) $matches[1], 'name' => $name];
            }
        }

        usort($indexed, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return array_map(static fn (array $part): string => $part['name'], $indexed);
    }

    /**
     * Resolves slide parts in true presentation order via the presentation
     * part's `<p:sldIdLst>` and its relationships. Returns null (so the caller
     * falls back to numeric filename order) when the ordering parts are absent,
     * malformed, or do not describe exactly the slide set already found by
     * filename, so a slide is never dropped or invented.
     *
     * @param  list<string>  $names
     * @param  list<string>  $filenameOrder
     * @return list<string>|null
     */
    private function presentationOrderedSlides(ZipArchive $archive, array $names, array $filenameOrder): ?array
    {
        try {
            $presentation = $this->readArchiveXml($archive, 'ppt/presentation.xml');
            $rels = $this->readArchiveXml($archive, 'ppt/_rels/presentation.xml.rels');
        } catch (IntakeAcquisitionFailure) {
            // A malformed or DOCTYPE-bearing ordering part is not worth failing
            // the whole deck over: fall back to numeric filename order. Each
            // slide part is still read through the hardened gate by the caller.
            return null;
        }

        if ($presentation === null || $rels === null) {
            return null;
        }

        $targets = $this->slideRelationshipTargets($rels, $names);
        $ordered = [];

        foreach ($presentation->getElementsByTagNameNS(self::PRESENTATION_NS, 'sldId') as $sldId) {
            $target = $targets[$sldId->getAttributeNS(self::RELATIONSHIP_NS, 'id')] ?? null;

            if ($target !== null) {
                $ordered[] = $target;
            }
        }

        // Trust the presentation order only when it is a strict reordering of
        // the slides found by filename: the same set, so nothing is dropped or
        // added.
        $left = $ordered;
        $right = $filenameOrder;
        sort($left);
        sort($right);

        return $left === $right ? $ordered : null;
    }

    /**
     * Maps each slide relationship id to its resolved part name, keeping only
     * targets that resolve inside the validated entry set.
     *
     * @param  list<string>  $names
     * @return array<string, string>
     */
    private function slideRelationshipTargets(DOMDocument $rels, array $names): array
    {
        $targets = [];

        foreach ($rels->getElementsByTagNameNS(self::PACKAGE_REL_NS, 'Relationship') as $relationship) {
            if (! str_ends_with($relationship->getAttribute('Type'), '/slide')) {
                continue;
            }

            $id = $relationship->getAttribute('Id');
            $resolved = $this->resolvePresentationTarget($relationship->getAttribute('Target'));

            if ($id !== '' && $resolved !== null && in_array($resolved, $names, true)) {
                $targets[$id] = $resolved;
            }
        }

        return $targets;
    }

    /**
     * Resolves a presentation relationship target to a package part name. The
     * target is relative to the presentation part's folder (`ppt`), or absolute
     * from the package root when it carries a leading slash. External targets
     * and traversal that escapes the package are rejected as null.
     */
    private function resolvePresentationTarget(string $target): ?string
    {
        if ($target === '' || str_contains($target, '://')) {
            return null;
        }

        $path = str_starts_with($target, '/') ? ltrim($target, '/') : 'ppt/'.$target;
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }
}

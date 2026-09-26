<?php

declare(strict_types=1);

namespace App\Domains\Intake\Support\Concerns;

use App\Domains\Intake\Enums\IntakeFailureCode;
use App\Domains\Intake\Exceptions\IntakeAcquisitionFailure;
use DOMDocument;
use DOMElement;
use DOMNode;
use Throwable;
use ZipArchive;

/**
 * Shared, hardened reader for OOXML packages (DOCX, PPTX). Both formats are ZIP
 * archives of XML parts, so both inherit the same two attack surfaces and both
 * are guarded here rather than in each extractor:
 *
 * - Decompression bombs. The declared uncompressed size of every entry is summed
 *   from the central directory and rejected against a cap BEFORE a single byte is
 *   inflated, and the entry count is capped independently so an archive of many
 *   tiny members cannot exhaust the walk.
 * - Path traversal. Entry names carrying '..' or an absolute/leading separator are
 *   rejected outright. Nothing here writes to disk, but a poisoned name must never
 *   reach a future caller that does.
 * - XXE. Parts are parsed with LIBXML_NONET, with the external entity loader
 *   disabled, without LIBXML_NOENT (so entity substitution never happens), and any
 *   part carrying a DOCTYPE is rejected. Genuine OOXML parts never declare one.
 */
trait ReadsOfficeArchive
{
    /**
     * Opens the package, applies every archive guard, and hands the caller a
     * validated archive. The scratch file is always removed.
     *
     * @param  callable(ZipArchive, list<string>): string  $reader  receives the archive and its validated entry names
     *
     * @throws IntakeAcquisitionFailure
     */
    protected function readOfficeArchive(string $rawContent, callable $reader): string
    {
        $scratch = tempnam(sys_get_temp_dir(), 'intake_ooxml_');

        if ($scratch === false) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::ExtractionFailed,
                'could not allocate a scratch file for document extraction',
            );
        }

        $archive = new ZipArchive;
        $opened = false;

        try {
            file_put_contents($scratch, $rawContent);
            $opened = $archive->open($scratch, ZipArchive::RDONLY) === true;

            if (! $opened) {
                throw new IntakeAcquisitionFailure(
                    IntakeFailureCode::ExtractionFailed,
                    'the document is not a readable Office package',
                );
            }

            return $reader($archive, $this->validatedEntryNames($archive));
        } catch (IntakeAcquisitionFailure $failure) {
            throw $failure;
        } catch (Throwable) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::ExtractionFailed,
                'the document could not be read',
            );
        } finally {
            if ($opened) {
                $archive->close();
            }

            @unlink($scratch);
        }
    }

    /**
     * Reads one XML part and returns its parsed document, or null when the part
     * is absent. Absence is normal (a PPTX without speaker notes); malformed or
     * hostile XML is not, and fails loudly.
     *
     * @throws IntakeAcquisitionFailure
     */
    protected function readArchiveXml(ZipArchive $archive, string $entryName): ?DOMDocument
    {
        $xml = $archive->getFromName($entryName, $this->maxUncompressedBytes());

        if (! is_string($xml) || trim($xml) === '') {
            return null;
        }

        $previousErrors = libxml_use_internal_errors(true);
        libxml_set_external_entity_loader(static fn (): null => null);

        try {
            $document = new DOMDocument;
            // LIBXML_NONET blocks network retrieval. LIBXML_NOENT is deliberately
            // absent: with it, libxml substitutes declared entities and an external
            // entity would be expanded into the extracted text.
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_set_external_entity_loader(null);
            libxml_use_internal_errors($previousErrors);
        }

        if ($loaded === false) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::ExtractionFailed,
                'the document contains malformed XML',
            );
        }

        if ($document->doctype !== null) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::ExtractionFailed,
                'the document declares an unsupported document type',
            );
        }

        return $document;
    }

    /**
     * Walks child elements in document order, invoking the visitor for each one.
     * Extractors supply the per-element semantics; traversal is shared.
     *
     * @param  callable(DOMElement): void  $enter
     * @param  callable(DOMElement): void  $leave
     */
    protected function walkElements(DOMNode $node, callable $enter, callable $leave): void
    {
        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $enter($child);
            $this->walkElements($child, $enter, $leave);
            $leave($child);
        }
    }

    /**
     * @return list<string>
     *
     * @throws IntakeAcquisitionFailure
     */
    private function validatedEntryNames(ZipArchive $archive): array
    {
        $entryCount = $archive->numFiles;

        if ($entryCount < 1 || $entryCount > $this->maxArchiveEntries()) {
            throw new IntakeAcquisitionFailure(
                IntakeFailureCode::ContentTooLarge,
                'the document contains too many parts to read safely',
            );
        }

        $maxUncompressed = $this->maxUncompressedBytes();
        $totalUncompressed = 0;
        $names = [];

        for ($index = 0; $index < $entryCount; $index++) {
            $stat = $archive->statIndex($index);

            if ($stat === false) {
                throw new IntakeAcquisitionFailure(
                    IntakeFailureCode::ExtractionFailed,
                    'the document could not be read',
                );
            }

            $name = (string) $stat['name'];

            if (str_contains($name, '..')
                || str_starts_with($name, '/')
                || str_starts_with($name, '\\')
                || str_contains($name, "\0")) {
                throw new IntakeAcquisitionFailure(
                    IntakeFailureCode::ExtractionFailed,
                    'the document contains an unsafe part name',
                );
            }

            $totalUncompressed += max(0, (int) $stat['size']);

            if ($totalUncompressed > $maxUncompressed) {
                throw new IntakeAcquisitionFailure(
                    IntakeFailureCode::ContentTooLarge,
                    'the document expands beyond the size limit',
                );
            }

            $names[] = $name;
        }

        return $names;
    }

    private function maxArchiveEntries(): int
    {
        return max(1, (int) config('intake.office.max_entries', 512));
    }

    private function maxUncompressedBytes(): int
    {
        return max(1, (int) config('intake.office.max_uncompressed_bytes', 64 * 1024 * 1024));
    }
}
